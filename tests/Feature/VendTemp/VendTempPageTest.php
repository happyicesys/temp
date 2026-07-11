<?php

use App\Models\Customer;
use App\Models\Device;
use App\Models\User;
use App\Models\VendTemp;

use function Pest\Laravel\actingAs;

test('guests cannot view the temperature page', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    $this->get(route('vend-temps.index', $device))
        ->assertRedirect(route('login'));
});

test('the temperature page renders with readings for the device', function () {
    $device = Device::factory()->for(Customer::factory())->create();
    VendTemp::factory()->for($device)->create(['recorded_at' => now()->subMinutes(5)]);
    VendTemp::factory()->for($device)->create(['recorded_at' => now()->subMinutes(10)]);
    VendTemp::factory()->for($device)->create(['recorded_at' => now()->subMinutes(15)]);

    actingAs(User::factory()->create())
        ->get(route('vend-temps.index', $device))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('VendTemps/Index')
            ->where('device.id', $device->id)
            ->has('readings', 3)
            ->has('typeLabels')
        );
});

test('readings are filtered by the requested time window', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    VendTemp::factory()->for($device)->create(['recorded_at' => now()->subDays(5)]);
    VendTemp::factory()->for($device)->create(['recorded_at' => now()->subHour()]);

    actingAs(User::factory()->create())
        ->get(route('vend-temps.index', [
            'device' => $device,
            'datetime_from' => now()->subDay()->toIso8601String(),
            'datetime_to' => now()->toIso8601String(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('readings', 1));
});

test('readings are exposed in celsius rather than the raw scaled integer', function () {
    $device = Device::factory()->for(Customer::factory())->create();
    VendTemp::factory()->for($device)->create([
        'value' => -185,
        'type' => VendTemp::TYPE_CHAMBER,
        'recorded_at' => now()->subMinutes(5),
    ]);

    actingAs(User::factory()->create())
        ->get(route('vend-temps.index', $device))
        ->assertInertia(fn ($page) => $page->where('readings.0.value', -18.5));
});
