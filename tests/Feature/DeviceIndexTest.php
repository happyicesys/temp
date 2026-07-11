<?php

use App\Models\Customer;
use App\Models\Device;
use App\Models\User;
use App\Models\VendTemp;

use function Pest\Laravel\actingAs;

test('guests cannot view the device list', function () {
    $this->get(route('devices.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users land on a device list', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    actingAs(User::factory()->create())
        ->get(route('devices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Index')
            ->has('devices', 1)
            ->where('devices.0.id', $device->id)
        );
});

test('the device list surfaces the latest reading in celsius', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    VendTemp::factory()->for($device)->create([
        'value' => -190,
        'recorded_at' => now()->subDay(),
    ]);
    VendTemp::factory()->for($device)->create([
        'value' => -181,
        'recorded_at' => now()->subMinutes(5),
    ]);

    actingAs(User::factory()->create())
        ->get(route('devices.index'))
        ->assertInertia(fn ($page) => $page->where('devices.0.latest.value', -18.1));
});

test('login redirects to the device list', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/devices');
});
