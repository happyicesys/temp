<?php

use App\Models\Customer;
use App\Models\Device;
use App\Services\Sensor\Jaalee\JaaleeApiClient;
use App\Services\Sensor\SensorApiException;
use App\Services\Sensor\SensorVendorClientResolver;

test('the default container resolver knows about Jaalee', function () {
    /** @var SensorVendorClientResolver $resolver */
    $resolver = app(SensorVendorClientResolver::class);

    $device = Device::factory()->for(Customer::factory())->create(['vendor' => 'jaalee']);

    expect($resolver->forDevice($device))->toBeInstanceOf(JaaleeApiClient::class);
});

test('resolver throws when asked for an unregistered vendor', function () {
    /** @var SensorVendorClientResolver $resolver */
    $resolver = app(SensorVendorClientResolver::class);

    $resolver->forVendor('acme-sensors');
})->throws(SensorApiException::class);
