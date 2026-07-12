<?php

use App\Models\Customer;
use App\Models\Device;
use App\Models\User;
use App\Models\VendTemp;

use function Pest\Laravel\actingAs;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats', 4)
            ->has('chart')
            ->has('devices')
            ->where('range', '24h')
        );
});

test('the dashboard counts every monitored device', function () {
    Device::factory()->count(3)->for(Customer::factory())->create();

    actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.0.value', '3'));
});

test('a device with a recent chamber reading is reported as online and normal', function () {
    $device = Device::factory()->for(Customer::factory())->create([
        'alert_low_temp' => -25,
        'alert_high_temp' => -15,
    ]);

    VendTemp::factory()->for($device)->create([
        'type' => VendTemp::TYPE_CHAMBER,
        'value' => -200, // -20.0°C, inside the alert band
        'recorded_at' => now()->subMinutes(2),
    ]);

    actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.status', 'ok')
            ->where('devices.0.value', -20.0)
            ->where('stats.1.value', '1') // reporting now
            ->where('stats.2.value', '0') // active alerts
        );
});

test('a device whose latest reading is older than ten minutes is offline', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    VendTemp::factory()->for($device)->create([
        'type' => VendTemp::TYPE_CHAMBER,
        'value' => -200,
        'recorded_at' => now()->subMinutes(30),
    ]);

    actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.status', 'offline')
            ->where('stats.1.value', '0')
        );
});

test('a reading outside the alert band raises an active alert', function () {
    $device = Device::factory()->for(Customer::factory())->create([
        'alert_low_temp' => -25,
        'alert_high_temp' => -15,
    ]);

    VendTemp::factory()->for($device)->create([
        'type' => VendTemp::TYPE_CHAMBER,
        'value' => 0, // 0.0°C, well above the -15°C ceiling
        'recorded_at' => now()->subMinute(),
    ]);

    actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.status', 'warn')
            ->where('stats.2.value', '1')
        );
});

test('the chamber chart is empty when there are no readings', function () {
    Device::factory()->for(Customer::factory())->create();

    actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('chart.series', [])
            ->where('chart.current', null)
        );
});

test('the chamber chart is bucketed for the selected range', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    VendTemp::factory()->for($device)->create([
        'type' => VendTemp::TYPE_CHAMBER,
        'value' => -200,
        'recorded_at' => now()->subMinutes(2),
    ]);

    actingAs(User::factory()->create());

    $this->get(route('dashboard', ['range' => '7d']))
        ->assertInertia(fn ($page) => $page
            ->where('range', '7d')
            ->has('chart.series', 7)
            ->has('chart.axisLabels', 7)
            ->where('chart.current', '-20.0')
        );
});

test('an unknown range is rejected', function () {
    actingAs(User::factory()->create());

    $this->get(route('dashboard', ['range' => 'nope']))
        ->assertSessionHasErrors('range');
});
