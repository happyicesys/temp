<?php

use App\Models\Customer;
use App\Models\Device;
use App\Models\VendTemp;

test('a vend temp belongs to a device', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    $reading = VendTemp::factory()->for($device)->create();

    expect($reading->device->is($device))->toBeTrue();
});

test('a device aggregates its vend temp readings through the relationship', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    VendTemp::factory()->count(4)->for($device)->create();

    expect($device->vendTemps()->count())->toBe(4);
});

test('celsius accessor scales the stored integer by ten', function () {
    $reading = VendTemp::factory()->make(['value' => 135]);

    expect($reading->celsius)->toBe(13.5);
});

test('celsius accessor returns null for the hardware error sentinel', function () {
    $reading = VendTemp::factory()->errored()->make();

    expect($reading->celsius)->toBeNull();
});

test('between scope restricts readings to the time window', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    VendTemp::factory()->for($device)->create(['recorded_at' => now()->subDays(10)]);
    $inRange = VendTemp::factory()->for($device)->create(['recorded_at' => now()->subHour()]);

    $results = VendTemp::query()
        ->where('device_id', $device->id)
        ->between(now()->subDay(), now())
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($inRange))->toBeTrue();
});

test('ofTypes scope filters by probe type', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    VendTemp::factory()->for($device)->create(['type' => VendTemp::TYPE_CHAMBER]);
    VendTemp::factory()->for($device)->evaporator()->create();

    $results = VendTemp::query()
        ->where('device_id', $device->id)
        ->ofTypes([VendTemp::TYPE_EVAPORATOR])
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->type)->toBe(VendTemp::TYPE_EVAPORATOR);
});
