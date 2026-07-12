<?php

use App\Models\Customer;
use App\Models\Device;
use App\Models\Operator;
use App\Models\Temp;

test('a device belongs to a customer and optionally to an operator', function () {
    $customer = Customer::factory()->create();
    $operator = Operator::factory()->create();

    $device = Device::factory()->for($customer)->withOperator($operator)->create();

    expect($device->customer->is($customer))->toBeTrue()
        ->and($device->operator->is($operator))->toBeTrue();
});

test('a device exposes its readings via the temps relationship', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    Temp::factory()->count(5)->for($device)->create();

    expect($device->temps()->count())->toBe(5);
});

test('latestTemp returns the most recent reading by recorded_at', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    Temp::factory()->for($device)->create([
        'recorded_at' => now()->subHour(),
        'temperature' => -20.0,
    ]);
    $latest = Temp::factory()->for($device)->create([
        'recorded_at' => now(),
        'temperature' => -22.0,
    ]);

    expect($device->latestTemp->is($latest))->toBeTrue();
});

test('alertEmailList splits and trims the comma-separated column', function () {
    $device = Device::factory()->for(Customer::factory())->create([
        'alert_emails' => ' ops@example.com , manager@example.com ,, ',
    ]);

    expect($device->alertEmailList())
        ->toBe(['ops@example.com', 'manager@example.com']);
});

test('alertEmailList is empty when the column is null or blank', function () {
    $device = Device::factory()->for(Customer::factory())->create(['alert_emails' => null]);
    expect($device->alertEmailList())->toBe([]);

    $device->update(['alert_emails' => '   ']);
    expect($device->fresh()->alertEmailList())->toBe([]);
});

test('hasFreshReading reflects the last reading age against the configured window', function () {
    config()->set('sensors.offline_after_seconds', 600);
    $device = Device::factory()->for(Customer::factory())->make();

    $device->last_reading_at = null;
    expect($device->hasFreshReading())->toBeFalse();

    $device->last_reading_at = now()->subMinutes(2);
    expect($device->hasFreshReading())->toBeTrue();

    $device->last_reading_at = now()->subMinutes(20);
    expect($device->hasFreshReading())->toBeFalse();
});
