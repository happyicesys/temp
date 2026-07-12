<?php

use App\Jobs\PollVendorAccountJob;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Temp;
use App\Services\Sensor\DeviceProvisioner;
use App\Services\Sensor\SensorVendorClientResolver;
use Illuminate\Support\Facades\Http;

/**
 * Run the poll job with both of its resolved dependencies.
 */
function runPoll(string $vendor = 'jaalee'): void
{
    (new PollVendorAccountJob($vendor))->handle(
        app(SensorVendorClientResolver::class),
        app(DeviceProvisioner::class),
    );
}

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.jaalee', [
        'base_url' => 'https://sensor.jaalee.com',
        'token' => 'test-token',
        'timeout' => 5,
    ]);
});

test('persists one Temp row per known device returned by the vendor', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [
                ['mac' => 'MAC-A', 'temperature' => -20.5, 'humidity' => 70.1, 'power' => 82, 'time' => 1747400000000],
                ['mac' => 'MAC-B', 'temperature' => 4.0, 'humidity' => 55.0, 'power' => 41, 'time' => 1747400000000],
            ],
        ], 200),
    ]);

    $customer = Customer::factory()->create();
    $a = Device::factory()->for($customer)->create(['vendor' => 'jaalee', 'vendor_device_id' => 'MAC-A']);
    $b = Device::factory()->for($customer)->create(['vendor' => 'jaalee', 'vendor_device_id' => 'MAC-B']);

    runPoll();

    expect(Temp::query()->count())->toBe(2);

    $tempA = Temp::query()->where('device_id', $a->id)->first();
    expect((float) $tempA->temperature)->toBe(-20.5)
        ->and((float) $tempA->humidity)->toBe(70.1)
        ->and($tempA->battery_level)->toBe(82);

    $a->refresh();
    $b->refresh();
    expect($a->last_polled_at)->not->toBeNull()
        ->and($a->last_reading_at)->not->toBeNull()
        ->and($b->last_polled_at)->not->toBeNull()
        // The denormalised cache the device list reads must mirror the reading.
        ->and((float) $a->last_temperature)->toBe(-20.5)
        ->and((float) $a->last_humidity)->toBe(70.1)
        ->and((float) $b->last_temperature)->toBe(4.0)
        ->and((float) $b->last_humidity)->toBe(55.0);
});

test('unknown vendor devices are ignored when auto-registration is off', function () {
    config()->set('sensors.auto_register', false);

    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [
                ['mac' => 'KNOWN', 'temperature' => -10.0, 'power' => 50, 'time' => 1747400000000],
                ['mac' => 'NEVER-SEEN', 'temperature' => -11.0, 'power' => 60, 'time' => 1747400000000],
            ],
        ], 200),
    ]);

    Device::factory()
        ->for(Customer::factory())
        ->create(['vendor' => 'jaalee', 'vendor_device_id' => 'KNOWN']);

    runPoll();

    expect(Temp::query()->count())->toBe(1)
        ->and(Device::query()->count())->toBe(1);
});

test('unknown vendor devices are auto-registered and logged when enabled', function () {
    config()->set('sensors.auto_register', true);

    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [
                ['mac' => 'C60F7D21C31E', 'alias' => 'brian logger 1', 'type' => 'F51C', 'temperature' => 30.05, 'humidity' => 80.59, 'power' => 100, 'time' => 1747400000000],
            ],
        ], 200),
    ]);

    runPoll();

    $device = Device::query()->where('vendor_device_id', 'C60F7D21C31E')->first();

    expect($device)->not->toBeNull()
        ->and($device->name)->toBe('brian logger 1')
        ->and($device->model)->toBe('F51C')
        ->and(Temp::query()->where('device_id', $device->id)->count())->toBe(1);

    $temp = Temp::query()->where('device_id', $device->id)->first();
    expect((float) $temp->temperature)->toBe(30.05)
        ->and($temp->battery_level)->toBe(100);
});

test('re-polling the same timestamp is idempotent (no duplicate rows)', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [
                ['mac' => 'MAC-A', 'temperature' => -20.5, 'time' => 1747400000000],
            ],
        ], 200),
    ]);

    Device::factory()
        ->for(Customer::factory())
        ->create(['vendor' => 'jaalee', 'vendor_device_id' => 'MAC-A']);

    runPoll();
    runPoll();

    expect(Temp::query()->count())->toBe(1);
});

test('an API error logs a warning and still touches last_polled_at', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response('boom', 500),
    ]);

    $device = Device::factory()
        ->for(Customer::factory())
        ->create(['vendor' => 'jaalee', 'vendor_device_id' => 'MAC-A']);

    runPoll();

    expect(Temp::query()->count())->toBe(0);

    $device->refresh();
    expect($device->last_polled_at)->not->toBeNull();
});

test('devices belonging to other vendors are untouched', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [['mac' => 'MAC-A', 'temperature' => -20.5, 'time' => 1747400000000]],
        ], 200),
    ]);

    $customer = Customer::factory()->create();
    $jaalee = Device::factory()->for($customer)->create(['vendor' => 'jaalee', 'vendor_device_id' => 'MAC-A']);
    $other = Device::factory()->for($customer)->create(['vendor' => 'acme', 'vendor_device_id' => 'OTHER-1']);

    runPoll();

    $other->refresh();
    expect($other->last_polled_at)->toBeNull();

    $jaalee->refresh();
    expect($jaalee->last_polled_at)->not->toBeNull();
});
