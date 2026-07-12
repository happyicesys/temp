<?php

use App\Models\Customer;
use App\Models\Device;
use App\Models\User;

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

test('the device list surfaces the cached latest temperature and humidity', function () {
    $device = Device::factory()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.1, humidity: 62.5, recordedAt: now()->subMinutes(5))
        ->create();

    actingAs(User::factory()->create())
        ->get(route('devices.index'))
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.id', $device->id)
            ->where('devices.0.latest.temperature', -18.1)
            ->where('devices.0.latest.humidity', 62.5)
            ->whereNot('devices.0.latest.recorded_at', null)
        );
});

test('the device list exposes a live online status from reading freshness', function () {
    $online = Device::factory()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.0, humidity: 60, recordedAt: now()->subMinutes(2))
        ->create(['name' => 'Fresh']);

    $offline = Device::factory()
        ->for(Customer::factory())
        ->withLatestReading(temperature: -18.0, humidity: 60, recordedAt: now()->subMinutes(30))
        ->create(['name' => 'Stale']);

    actingAs(User::factory()->create())
        ->get(route('devices.index'))
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.id', $online->id)
            ->where('devices.0.is_online', true)
            ->where('devices.1.id', $offline->id)
            ->where('devices.1.is_online', false)
        );
});

test('the device list reports no reading when the cache is empty', function () {
    Device::factory()->for(Customer::factory())->create();

    actingAs(User::factory()->create())
        ->get(route('devices.index'))
        ->assertInertia(fn ($page) => $page->where('devices.0.latest', null));
});

test('login redirects to the device list', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/devices');
});
