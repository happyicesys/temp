<?php

namespace App\Services\Sensor;

use DateTimeImmutable;

/**
 * Vendor-neutral DTO representing a single temperature/state sample.
 *
 * Each concrete vendor client maps its proprietary payload onto this shape
 * so the rest of the app never has to care which manufacturer produced the
 * data. Device-type-specific extras (CO2, PM2.5, UV, etc.) are preserved
 * verbatim in {@see $rawPayload} for later inspection or charting.
 */
class SensorReading
{
    /**
     * @param  string  $vendorDeviceId  Vendor's unique identifier for the device
     *                                  (e.g. Jaalee's BLE MAC). Matches the
     *                                  `devices.vendor_device_id` column.
     * @param  array<string, mixed>  $rawPayload  Untouched upstream payload.
     */
    public function __construct(
        public readonly string $vendorDeviceId,
        public readonly ?float $temperature,
        public readonly ?float $humidity,
        public readonly ?float $pressure,
        public readonly ?int $batteryLevel,
        public readonly bool $isOnline,
        public readonly DateTimeImmutable $recordedAt,
        public readonly array $rawPayload,
    ) {}

    /**
     * Convert this DTO into the array shape expected by `Temp::create()`.
     *
     * Note that `device_id` is *not* set here — the caller resolves the
     * local Device row by matching `vendorDeviceId` and merges it in.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'temperature' => $this->temperature,
            'humidity' => $this->humidity,
            'pressure' => $this->pressure,
            'battery_level' => $this->batteryLevel,
            'is_online' => $this->isOnline,
            'recorded_at' => $this->recordedAt,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
