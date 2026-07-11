<?php

namespace App\Services\Sensor;

use App\Models\Customer;
use App\Models\Device;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Creates local Device rows on demand for units the vendor reports but that
 * we don't yet track.
 *
 * Kept out of {@see \App\Jobs\PollVendorAccountJob} so the "should we adopt an
 * unknown device, and with what defaults?" policy lives in one testable place
 * instead of being tangled into the polling loop. The poll job simply asks the
 * provisioner for a Device and persists a reading against it.
 */
class DeviceProvisioner
{
    public function __construct(
        private readonly Config $config,
    ) {}

    /**
     * Whether the poll should adopt unknown devices automatically.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->config->get('sensors.auto_register', true);
    }

    /**
     * Find or create the local Device for a freshly seen vendor reading.
     *
     * Idempotent: keyed on (vendor, vendor_device_id), which is the same
     * unique constraint the devices table enforces, so a race between two
     * poll workers can't create duplicates.
     */
    public function provision(string $vendor, SensorReading $reading): Device
    {
        return Device::query()->firstOrCreate(
            [
                'vendor' => $vendor,
                'vendor_device_id' => $reading->vendorDeviceId,
            ],
            [
                'name' => $this->resolveName($reading),
                'model' => $this->resolveModel($reading),
                'customer_id' => $this->defaultCustomer()->getKey(),
                'is_active' => true,
            ],
        );
    }

    /**
     * The placeholder customer auto-registered devices are attached to.
     * Created on demand so a brand-new install never blocks a poll on a
     * missing owner.
     */
    public function defaultCustomer(): Customer
    {
        $defaults = $this->config->get('sensors.default_customer', []);
        $code = is_string($defaults['code'] ?? null) ? $defaults['code'] : 'UNASSIGNED';
        $name = is_string($defaults['name'] ?? null) ? $defaults['name'] : 'Unassigned';

        return Customer::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'is_active' => true],
        );
    }

    /**
     * Prefer the vendor's alias (e.g. "brian logger 1"); fall back to the
     * device identifier so the row is never nameless.
     */
    private function resolveName(SensorReading $reading): string
    {
        $alias = $reading->rawPayload['alias'] ?? null;

        if (is_string($alias) && trim($alias) !== '') {
            return trim($alias);
        }

        return $reading->vendorDeviceId;
    }

    private function resolveModel(SensorReading $reading): ?string
    {
        $type = $reading->rawPayload['type'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }
}
