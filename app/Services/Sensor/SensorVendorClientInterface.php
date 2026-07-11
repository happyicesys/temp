<?php

namespace App\Services\Sensor;

/**
 * Contract every hardware-vendor client must satisfy.
 *
 * Coding to this interface lets the rest of the app stay agnostic of the
 * specific manufacturer and makes it cheap to add a second vendor: subclass
 * {@see AbstractSensorVendorClient} or implement this interface directly.
 *
 * The contract is designed around an "account-wide fetch" because that is
 * what real IoT vendors typically expose and what real rate limits target.
 * A vendor that only offers per-device endpoints can still implement this
 * by looping over the configured devices internally.
 */
interface SensorVendorClientInterface
{
    /**
     * The short vendor identifier this client handles (e.g. "jaalee").
     *
     * Matches the `vendor` column on the `devices` table.
     */
    public function vendor(): string;

    /**
     * Fetch the latest reading for every device available on the configured
     * account.
     *
     * The result is keyed by the vendor's own device identifier (MAC,
     * serial, etc. — whatever populates {@see SensorReading::$vendorDeviceId})
     * so the caller can match readings to local Device rows without a
     * second lookup.
     *
     * Implementations must throw {@see SensorApiException} on auth
     * failures, transport failures, or upstream rate limiting so the caller
     * can decide how to retry or alert.
     *
     * @return array<string, SensorReading>
     */
    public function fetchAllReadings(): array;
}
