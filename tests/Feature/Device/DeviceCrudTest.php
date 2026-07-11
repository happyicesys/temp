<?php

use App\Models\Customer;
use App\Models\Device;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * A minimal valid create/update payload, overridable per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function devicePayload(Customer $customer, array $overrides = []): array
{
    return array_merge([
        'vendor' => 'jaalee',
        'vendor_device_id' => 'C60F7D21C31E',
        'name' => 'Brian Logger 1',
        'location' => 'Warehouse 3',
        'model' => 'F51C',
        'customer_id' => $customer->id,
        'operator_id' => null,
        'is_active' => true,
        'alert_low_temp' => -25,
        'alert_high_temp' => -15,
        'alert_emails' => 'ops@example.com',
        'alert_phones' => null,
    ], $overrides);
}

test('guests cannot reach device management routes', function () {
    $this->get(route('devices.create'))->assertRedirect(route('login'));

    $device = Device::factory()->for(Customer::factory())->create();
    $this->get(route('devices.edit', $device))->assertRedirect(route('login'));
    $this->delete(route('devices.destroy', $device))->assertRedirect(route('login'));
});

test('the create form renders with customer and operator options', function () {
    $customer = Customer::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('devices.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Create')
            ->has('customers', 1)
            ->where('customers.0.id', $customer->id)
            ->has('operators')
        );
});

test('a device can be created with valid data', function () {
    $customer = Customer::factory()->create();

    actingAs(User::factory()->create())
        ->post(route('devices.store'), devicePayload($customer))
        ->assertRedirect(route('devices.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('devices', [
        'vendor' => 'jaalee',
        'vendor_device_id' => 'C60F7D21C31E',
        'name' => 'Brian Logger 1',
        'customer_id' => $customer->id,
    ]);
});

test('creating a device requires a name, device id and customer', function () {
    actingAs(User::factory()->create())
        ->post(route('devices.store'), [
            'vendor' => 'jaalee',
        ])
        ->assertSessionHasErrors(['vendor_device_id', 'name', 'customer_id']);
});

test('vendor_device_id must be unique within a vendor', function () {
    $customer = Customer::factory()->create();
    Device::factory()->for($customer)->create([
        'vendor' => 'jaalee',
        'vendor_device_id' => 'DUP-MAC',
    ]);

    actingAs(User::factory()->create())
        ->post(route('devices.store'), devicePayload($customer, ['vendor_device_id' => 'DUP-MAC']))
        ->assertSessionHasErrors('vendor_device_id');
});

test('the same device id is allowed under a different vendor', function () {
    $customer = Customer::factory()->create();
    Device::factory()->for($customer)->create([
        'vendor' => 'jaalee',
        'vendor_device_id' => 'SHARED',
    ]);

    actingAs(User::factory()->create())
        ->post(route('devices.store'), devicePayload($customer, [
            'vendor' => 'acme',
            'vendor_device_id' => 'SHARED',
        ]))
        ->assertRedirect(route('devices.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('devices', ['vendor' => 'acme', 'vendor_device_id' => 'SHARED']);
});

test('the edit form renders the selected device', function () {
    $device = Device::factory()->for(Customer::factory())->create(['name' => 'Old Name']);

    actingAs(User::factory()->create())
        ->get(route('devices.edit', $device))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Edit')
            ->where('device.id', $device->id)
            ->where('device.name', 'Old Name')
        );
});

test('a device can be updated', function () {
    $customer = Customer::factory()->create();
    $device = Device::factory()->for($customer)->create([
        'vendor' => 'jaalee',
        'vendor_device_id' => 'KEEP-ID',
        'name' => 'Old Name',
    ]);

    actingAs(User::factory()->create())
        ->put(route('devices.update', $device), devicePayload($customer, [
            'vendor_device_id' => 'KEEP-ID',
            'name' => 'New Name',
            'is_active' => false,
        ]))
        ->assertRedirect(route('devices.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'name' => 'New Name',
        'is_active' => false,
    ]);
});

test('updating a device keeps its own unchanged device id valid', function () {
    $customer = Customer::factory()->create();
    $device = Device::factory()->for($customer)->create([
        'vendor' => 'jaalee',
        'vendor_device_id' => 'SELF-ID',
    ]);

    actingAs(User::factory()->create())
        ->put(route('devices.update', $device), devicePayload($customer, [
            'vendor_device_id' => 'SELF-ID',
        ]))
        ->assertSessionHasNoErrors();
});

test('a device can be deleted', function () {
    $device = Device::factory()->for(Customer::factory())->create();

    actingAs(User::factory()->create())
        ->delete(route('devices.destroy', $device))
        ->assertRedirect(route('devices.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});
