<?php

use App\Models\Customer;
use App\Models\Device;
use App\Services\Sensor\DeviceProvisioner;
use App\Services\Sensor\SensorReading;

/**
 * Build a minimal SensorReading for a given device, letting individual tests
 * override the raw payload (alias / type) that provisioning reads from.
 *
 * @param  array<string, mixed>  $rawExtras
 */
function jaaleeReading(string $mac, array $rawExtras = []): SensorReading
{
    return new SensorReading(
        vendorDeviceId: $mac,
        temperature: -18.0,
        humidity: 50.0,
        pressure: null,
        batteryLevel: 90,
        isOnline: true,
        recordedAt: new DateTimeImmutable('@1747400000'),
        rawPayload: array_merge(['mac' => $mac], $rawExtras),
    );
}

test('provision creates a device using the vendor alias and type', function () {
    $device = app(DeviceProvisioner::class)->provision(
        'jaalee',
        jaaleeReading('C60F7D21C31E', ['alias' => 'brian logger 1', 'type' => 'F51C']),
    );

    expect($device->exists)->toBeTrue()
        ->and($device->name)->toBe('brian logger 1')
        ->and($device->model)->toBe('F51C')
        ->and($device->vendor)->toBe('jaalee')
        ->and($device->vendor_device_id)->toBe('C60F7D21C31E')
        ->and($device->is_active)->toBeTrue();
});

test('provision falls back to the device id when no alias is supplied', function () {
    $device = app(DeviceProvisioner::class)->provision('jaalee', jaaleeReading('MAC-XYZ'));

    expect($device->name)->toBe('MAC-XYZ')
        ->and($device->model)->toBeNull();
});

test('provision attaches the default Unassigned customer', function () {
    $device = app(DeviceProvisioner::class)->provision('jaalee', jaaleeReading('MAC-1'));

    $customer = Customer::query()->find($device->customer_id);

    expect($customer)->not->toBeNull()
        ->and($customer->code)->toBe('UNASSIGNED')
        ->and($customer->name)->toBe('Unassigned');
});

test('provision reuses the default customer across multiple devices', function () {
    $provisioner = app(DeviceProvisioner::class);
    $provisioner->provision('jaalee', jaaleeReading('MAC-1'));
    $provisioner->provision('jaalee', jaaleeReading('MAC-2'));

    expect(Customer::query()->where('code', 'UNASSIGNED')->count())->toBe(1);
});

test('provision is idempotent for the same vendor + device id', function () {
    $provisioner = app(DeviceProvisioner::class);

    $first = $provisioner->provision('jaalee', jaaleeReading('MAC-DUP'));
    $second = $provisioner->provision('jaalee', jaaleeReading('MAC-DUP'));

    expect($second->id)->toBe($first->id)
        ->and(Device::query()->count())->toBe(1);
});

test('isEnabled reflects the sensors.auto_register config flag', function () {
    config()->set('sensors.auto_register', false);
    expect(app(DeviceProvisioner::class)->isEnabled())->toBeFalse();

    config()->set('sensors.auto_register', true);
    expect(app(DeviceProvisioner::class)->isEnabled())->toBeTrue();
});
