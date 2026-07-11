<?php

namespace App\Services\Sensor\Jaalee;

use App\Services\Sensor\AbstractSensorVendorClient;
use App\Services\Sensor\SensorApiException;
use Throwable;

/**
 * Concrete client for the Jaalee Open API.
 *
 * Docs:    https://sensor.jaalee.com/open/
 * Auth:    Authorization header carries a token obtained via POST
 *          /v1/open/login. The token is permanently valid until the
 *          account logs in again, so we keep it in .env rather than
 *          re-acquiring it each call.
 * Limit:   1 request / minute / account on the data endpoints. Contact
 *          dev@jaalee.com to raise the cap.
 * Format:  every endpoint wraps the payload in {code, message, data}.
 *          code === 0 means success, code === 3 means the token is
 *          invalid (the user must obtain a new one via the login flow).
 */
class JaaleeApiClient extends AbstractSensorVendorClient
{
    /** Jaalee response code for a successful request. */
    private const CODE_SUCCESS = 0;

    /** Jaalee response code for an invalid / expired token. */
    private const CODE_INVALID_TOKEN = 3;

    public function vendor(): string
    {
        return 'jaalee';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRawDevices(): array
    {
        $response = $this->get('/v1/open/data/all');

        if (! $response->successful()) {
            throw new SensorApiException(
                "Jaalee API returned HTTP {$response->status()}: {$response->body()}",
            );
        }

        try {
            $body = $response->json();
        } catch (Throwable $e) {
            throw new SensorApiException('Unparseable JSON from Jaalee API', previous: $e);
        }

        if (! is_array($body)) {
            throw new SensorApiException('Jaalee API returned a non-object response.');
        }

        // Jaalee uses string codes in some places ("0") and ints in others.
        $code = (int) ($body['code'] ?? -1);

        if ($code === self::CODE_INVALID_TOKEN) {
            throw new SensorApiException(
                'Jaalee API token is invalid — log in again via POST /v1/open/login and update JAALEE_API_TOKEN.',
            );
        }

        if ($code !== self::CODE_SUCCESS) {
            $message = $body['message'] ?? '';
            throw new SensorApiException("Jaalee API error (code {$code}): {$message}");
        }

        $data = $body['data'] ?? [];

        if (! is_array($data)) {
            throw new SensorApiException('Jaalee API success response had no data array.');
        }

        return $data;
    }

    /**
     * Map one Jaalee device payload onto our normalised key set.
     *
     * Field reference (from the Jaalee docs):
     *   mac          — BLE MAC, our vendor_device_id
     *   type         — model code (F523, F525, F526, F527, F534, F535, …)
     *   alias        — device name
     *   power        — battery 0..100
     *   time         — update time in milliseconds
     *   temperature  — °C
     *   humidity     — %
     *   pressure     — pressure value
     *   (plus device-type-specific fields: co2, pm25, pm10, light, uv,
     *    hchoPpm, tvocPpm, vocIndex, vocRaw, nh3, o3Ppm, …)
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mapDevicePayload(array $payload): array
    {
        return [
            'vendor_device_id' => $payload['mac'] ?? null,
            'temperature' => $payload['temperature'] ?? null,
            'humidity' => $payload['humidity'] ?? null,
            'pressure' => $payload['pressure'] ?? null,
            'battery_level' => $payload['power'] ?? null,
            // Jaalee does not surface an explicit online flag in /data/all;
            // the staleness of `time` is the closest proxy. We default to
            // true and let a future enhancement compute is_online by
            // comparing the timestamp against `now()` if needed.
            'is_online' => true,
            'recorded_at' => $payload['time'] ?? null,
        ];
    }
}
