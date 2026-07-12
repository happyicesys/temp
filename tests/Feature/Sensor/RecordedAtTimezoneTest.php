<?php

use App\Http\Resources\TempResource;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Temp;
use Illuminate\Support\Carbon;

/**
 * Regression coverage for the "recorded_at keeps jumping" timezone bug.
 *
 * Root cause was a TIMESTAMP column read back under a non-UTC app timezone,
 * so a value written as UTC was re-interpreted in Asia/Singapore (+08:00).
 * The fix stores everything in UTC and lets the UI localise per operator.
 */
test('the application timezone is UTC so writes and reads stay symmetric', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

test('recorded_at round-trips as the exact same UTC instant with no drift', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    // 1747400000000 epoch-ms is what Jaalee reports for this sample.
    $instant = Carbon::createFromTimestamp(1747400000, 'UTC');

    $temp = Temp::create([
        'device_id' => $device->id,
        'temperature' => 29.98,
        'humidity' => 79.45,
        'is_online' => true,
        'recorded_at' => $instant,
    ]);

    $fresh = Temp::findOrFail($temp->id);

    expect($fresh->recorded_at->equalTo($instant))->toBeTrue()
        ->and($fresh->recorded_at->getTimestamp())->toBe(1747400000)
        ->and($fresh->recorded_at->toIso8601String())->toEndWith('+00:00');
});

test('TempResource exposes recorded_at as a UTC ISO-8601 string', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    $temp = Temp::factory()->for($device)->create([
        'recorded_at' => Carbon::createFromTimestamp(1747400000, 'UTC'),
    ]);

    $payload = (new TempResource($temp))->toArray(request());

    expect($payload['recorded_at'])->toBe('2025-05-16T12:53:20+00:00');
});
