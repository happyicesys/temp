<?php

namespace App\Services\Sensor\Jaalee;

use App\Services\Sensor\SensorApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * One-time bootstrap helper that obtains a Jaalee Open API token.
 *
 * Flow (per https://sensor.jaalee.com/open/ sections 01 + 02):
 *
 *   1. {@see requestCode()} — calls GET /v1/open/code?account=...
 *      Jaalee then SMS / emails a verification code to that account.
 *   2. Operator reads the code from their inbox / phone.
 *   3. {@see login()}      — calls POST /v1/open/login with
 *      {account, code, timeZone} and returns the issued token.
 *
 * The token is permanently valid until the next login, so we run this
 * once, drop the value into JAALEE_API_TOKEN in .env, and forget about it.
 *
 * Kept as a separate class from {@see JaaleeApiClient} because login has a
 * fundamentally different lifecycle (one-off, interactive, no token yet)
 * from polling (recurring, headless, authenticated).
 */
class JaaleeAuthenticator
{
    public function __construct(
        protected HttpFactory $http,
        protected string $baseUrl,
        protected int $timeoutSeconds = 10,
    ) {}

    /**
     * Ask Jaalee to send a verification code to the given account.
     *
     * @param  string  $account  email or phone (per Jaalee docs)
     */
    public function requestCode(string $account): void
    {
        try {
            $response = $this->client()->get('/v1/open/code', ['account' => $account]);
        } catch (ConnectionException $e) {
            throw new SensorApiException(
                "Connection to Jaalee API failed while requesting code: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! $response->successful()) {
            throw new SensorApiException(
                "Jaalee /v1/open/code returned HTTP {$response->status()}: {$response->body()}",
            );
        }

        $body = $this->decodeBody($response->body());
        $code = (int) ($body['code'] ?? -1);

        if ($code !== 0) {
            $message = $body['message'] ?? '';
            throw new SensorApiException("Jaalee refused to send a verification code (code {$code}): {$message}");
        }
    }

    /**
     * Exchange a verification code for a permanent API token.
     *
     * @param  string  $account   email or phone the code was sent to
     * @param  string  $code      verification code from the SMS / email
     * @param  string  $timeZone  e.g. "Asia/Singapore" or "GMT+08:00"
     * @return string             the issued token
     */
    public function login(string $account, string $code, string $timeZone): string
    {
        try {
            $response = $this->client()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('/v1/open/login', [
                    'account' => $account,
                    'code' => $code,
                    'timeZone' => $timeZone,
                ]);
        } catch (ConnectionException $e) {
            throw new SensorApiException(
                "Connection to Jaalee API failed while logging in: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! $response->successful()) {
            throw new SensorApiException(
                "Jaalee /v1/open/login returned HTTP {$response->status()}: {$response->body()}",
            );
        }

        $body = $this->decodeBody($response->body());
        $apiCode = (int) ($body['code'] ?? -1);

        if ($apiCode !== 0) {
            $message = $body['message'] ?? '';
            throw new SensorApiException("Jaalee login failed (code {$apiCode}): {$message}");
        }

        $token = $body['data']['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new SensorApiException('Jaalee login succeeded but no token was returned.');
        }

        return $token;
    }

    protected function client(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeoutSeconds)
            ->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeBody(string $body): array
    {
        try {
            $decoded = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new SensorApiException('Unparseable JSON from Jaalee API.', previous: $e);
        }

        if (! is_array($decoded)) {
            throw new SensorApiException('Jaalee API returned a non-object response.');
        }

        return $decoded;
    }
}
