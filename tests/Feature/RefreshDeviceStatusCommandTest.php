<?php

use App\Models\Customer;
use App\Models\Device;

test('a device with a fresh reading is marked online', function () {
    $device = Device::factory()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.0, humidity: 60, recordedAt: now()->subMinutes(2))
        ->create(['is_online' => false, 'went_offline_at' => now()->subHour()]);

    $this->artisan('devices:refresh-status')->assertSuccessful();

    $device->refresh();
    expect($device->is_online)->toBeTrue()
        ->and($device->went_offline_at)->toBeNull();
});

test('a device whose last reading is stale is marked offline with a timestamp', function () {
    $device = Device::factory()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.0, humidity: 60, recordedAt: now()->subMinutes(20))
        ->create(['is_online' => true, 'went_offline_at' => null]);

    $this->artisan('devices:refresh-status')->assertSuccessful();

    $device->refresh();
    expect($device->is_online)->toBeFalse()
        ->and($device->went_offline_at)->not->toBeNull();
});

test('a device that never reported is treated as offline', function () {
    $device = Device::factory()
        ->for(Customer::factory())
        ->create(['is_online' => true]);

    $this->artisan('devices:refresh-status')->assertSuccessful();

    expect($device->refresh()->is_online)->toBeFalse();
});

test('recovery clears the offline timestamp', function () {
    $device = Device::factory()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.0, humidity: 60, recordedAt: now())
        ->create(['is_online' => false, 'went_offline_at' => now()->subHours(3)]);

    $this->artisan('devices:refresh-status')->assertSuccessful();

    $device->refresh();
    expect($device->is_online)->toBeTrue()
        ->and($device->went_offline_at)->toBeNull();
});

test('the offline timestamp is not overwritten while the device stays offline', function () {
    $offlineSince = now()->subHours(2);
    $device = Device::factory()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.0, humidity: 60, recordedAt: now()->subMinutes(30))
        ->create(['is_online' => false, 'went_offline_at' => $offlineSince]);

    $this->artisan('devices:refresh-status')->assertSuccessful();

    // Already offline and unchanged, so the original went_offline_at stands.
    expect($device->refresh()->went_offline_at->timestamp)->toBe($offlineSince->timestamp);
});

test('inactive devices are left untouched', function () {
    $device = Device::factory()
        ->inactive()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.0, humidity: 60, recordedAt: now()->subMinutes(20))
        ->create(['is_online' => true, 'went_offline_at' => null]);

    $this->artisan('devices:refresh-status')->assertSuccessful();

    // A paused device is expected to be silent; the check must not flip it.
    expect($device->refresh()->is_online)->toBeTrue();
});
