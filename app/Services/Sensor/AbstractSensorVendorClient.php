<?php

namespace App\Services\Sensor;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Template-method base class for HTTP-based sensor vendor clients.
 *
 * This class owns the parts every vendor needs: HTTP plumbing, error
 * handling, payload normalisation, and DTO construction. Concrete vendor
 * subclasses only declare three things:
 *   - {@see vendor()}            — the string identifier on devices.vendor
 *   - {@see fetchRawDevices()}   — calls the upstream endpoint, returns the
 *                                  array of raw device payloads
 *   - {@see mapDevicePayload()}  — translates one raw payload into our
 *                                  normalised key set
 *
 * Subclasses may also override {@see authHeaders()} when a vendor needs
 * non-standard auth (signed requests, OAuth, etc.) and the small `to*`
 * helpers if a vendor uses unusual encodings.
 */
abstract class AbstractSensorVendorClient implements SensorVendorClientInterface
{
    public function __construct(
        protected HttpFactory $http,
        protected string $baseUrl,
        protected string $token,
        protected int $timeoutSeconds = 10,
    ) {}

    /**
     * Call the upstream endpoint that returns all devices for the account
     * and return the array of raw per-device payloads.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function fetchRawDevices(): array;

    /**
     * Translate one device's raw payload into our normalised key set.
     *
     * Expected output keys (all optional except vendor_device_id):
     *   vendor_device_id (string, required)
     *   temperature, humidity, pressure (float)
     *   battery_level (int 0-100)
     *   is_online (bool)
     *   recorded_at (ISO string | unix seconds | unix ms)
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    abstract protected function mapDevicePayload(array $payload): array;

    public function fetchAllReadings(): array
    {
        $rawDevices = $this->fetchRawDevices();
        $readings = [];

        foreach ($rawDevices as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $mapped = $this->mapDevicePayload($raw);
            $vendorDeviceId = $mapped['vendor_device_id'] ?? null;

            if (! is_string($vendorDeviceId) || $vendorDeviceId === '') {
                // No identifier => can't match to a local device. Skip.
                continue;
            }

            $readings[$vendorDeviceId] = $this->buildReading($vendorDeviceId, $mapped, $raw);
        }

        return $readings;
    }

    /**
     * Build the underlying HTTP client with shared auth + timeout config.
     */
    protected function client(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->withHeaders($this->authHeaders());
    }

    /**
     * Default auth: send the token in the Authorization header verbatim
     * (no "Bearer " prefix — that matches Jaalee's docs). Subclasses may
     * override for vendors that use different auth schemes.
     *
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        return ['Authorization' => $this->token];
    }

    /**
     * Centralised GET + error handling helper for subclasses.
     */
    protected function get(string $path): Response
    {
        try {
            return $this->client()->get($path);
        } catch (ConnectionException $e) {
            throw new SensorApiException(
                "Connection to {$this->vendor()} API failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $mapped       output of mapDevicePayload()
     * @param  array<string, mixed>  $rawPayload   full untouched device payload
     */
    protected function buildReading(string $vendorDeviceId, array $mapped, array $rawPayload): SensorReading
    {
        return new SensorReading(
            vendorDeviceId: $vendorDeviceId,
            temperature: $this->toFloat($mapped['temperature'] ?? null),
            humidity: $this->toFloat($mapped['humidity'] ?? null),
            pressure: $this->toFloat($mapped['pressure'] ?? null),
            batteryLevel: $this->toBatteryLevel($mapped['battery_level'] ?? null),
            isOnline: (bool) ($mapped['is_online'] ?? true),
            recordedAt: $this->parseTimestamp($mapped['recorded_at'] ?? null),
            rawPayload: $rawPayload,
        );
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Battery is always 0..100. Clamp defensively.
     */
    protected function toBatteryLevel(mixed $value): ?int
    {
        $int = $this->toInt($value);

        if ($int === null) {
            return null;
        }

        return max(0, min(100, $int));
    }

    /**
     * Accept ISO-8601 strings, unix seconds, or unix milliseconds.
     */
    protected function parseTimestamp(mixed $value): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        if (is_numeric($value)) {
            $seconds = (int) $value;

            // Heuristic: anything past year ~2286 is almost certainly ms.
            if ($seconds > 10_000_000_000) {
                $seconds = intdiv($seconds, 1000);
            }

            return (new DateTimeImmutable('@'.$seconds))->setTimezone(new DateTimeZone('UTC'));
        }

        try {
            return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
    }
}
