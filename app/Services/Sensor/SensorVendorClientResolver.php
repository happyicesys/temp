<?php

namespace App\Services\Sensor;

use App\Models\Device;

/**
 * Maps a Device (or vendor string) to the correct SensorVendorClient.
 *
 * Each vendor registers itself once at boot time; the rest of the app then
 * just asks the resolver for a client. This is what keeps the polling job
 * vendor-agnostic.
 */
class SensorVendorClientResolver
{
    /**
     * @var array<string, SensorVendorClientInterface>
     */
    protected array $clients = [];

    public function register(SensorVendorClientInterface $client): void
    {
        $this->clients[strtolower($client->vendor())] = $client;
    }

    public function forDevice(Device $device): SensorVendorClientInterface
    {
        return $this->forVendor($device->vendor);
    }

    public function forVendor(string $vendor): SensorVendorClientInterface
    {
        $key = strtolower($vendor);

        if (! isset($this->clients[$key])) {
            throw new SensorApiException("No sensor client registered for vendor [{$vendor}].");
        }

        return $this->clients[$key];
    }

    /**
     * @return array<string, SensorVendorClientInterface>
     */
    public function all(): array
    {
        return $this->clients;
    }
}
