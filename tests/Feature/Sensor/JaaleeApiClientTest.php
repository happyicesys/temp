<?php

use App\Services\Sensor\Jaalee\JaaleeApiClient;
use App\Services\Sensor\SensorApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function makeJaaleeClient(string $token = 'test-token'): JaaleeApiClient
{
    return new JaaleeApiClient(
        http: app(HttpFactory::class),
        baseUrl: 'https://sensor.jaalee.com',
        token: $token,
        timeoutSeconds: 5,
    );
}

test('vendor identifier matches the devices.vendor column value', function () {
    expect(makeJaaleeClient()->vendor())->toBe('jaalee');
});

test('fetchAllReadings calls /v1/open/data/all with the configured token', function () {
    Http::fake([
        'sensor.jaalee.com/v1/open/data/all' => Http::response([
            'code' => 0,
            'message' => '',
            'data' => [],
        ], 200),
    ]);

    makeJaaleeClient('SECRET-TOKEN-XYZ')->fetchAllReadings();

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_ends_with($request->url(), '/v1/open/data/all')
            && $request->hasHeader('Authorization', 'SECRET-TOKEN-XYZ');
    });
});

test('parses a Jaalee device payload into a SensorReading keyed by mac', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'message' => '',
            'data' => [
                [
                    'mac' => 'AA:BB:CC:DD:EE:01',
                    'type' => 'F527',
                    'alias' => 'PSA Cold Room A',
                    'power' => 87,
                    'time' => 1747400000000, // ms
                    'temperature' => -21.7,
                    'humidity' => 64.5,
                    'pressure' => 1012.3,
                ],
            ],
        ], 200),
    ]);

    $readings = makeJaaleeClient()->fetchAllReadings();

    expect($readings)->toHaveKey('AA:BB:CC:DD:EE:01');

    $reading = $readings['AA:BB:CC:DD:EE:01'];
    expect($reading->vendorDeviceId)->toBe('AA:BB:CC:DD:EE:01')
        ->and($reading->temperature)->toBe(-21.7)
        ->and($reading->humidity)->toBe(64.5)
        ->and($reading->pressure)->toBe(1012.3)
        ->and($reading->batteryLevel)->toBe(87)
        ->and($reading->isOnline)->toBeTrue()
        ->and($reading->recordedAt->getTimestamp())->toBe(1747400000)
        ->and($reading->rawPayload['type'])->toBe('F527');
});

test('returns one reading per device when the account has multiple', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [
                ['mac' => 'M-1', 'temperature' => -10.0, 'power' => 90, 'time' => 1747400000000],
                ['mac' => 'M-2', 'temperature' => -22.5, 'power' => 50, 'time' => 1747400000000],
                ['mac' => 'M-3', 'temperature' => 4.2, 'power' => 12, 'time' => 1747400000000],
            ],
        ], 200),
    ]);

    $readings = makeJaaleeClient()->fetchAllReadings();

    expect($readings)->toHaveCount(3)
        ->and(array_keys($readings))->toEqualCanonicalizing(['M-1', 'M-2', 'M-3'])
        ->and($readings['M-2']->temperature)->toBe(-22.5);
});

test('skips entries that have no mac', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [
                ['temperature' => -10.0, 'time' => 1747400000000],
                ['mac' => 'GOOD', 'temperature' => -11.0, 'time' => 1747400000000],
            ],
        ], 200),
    ]);

    $readings = makeJaaleeClient()->fetchAllReadings();

    expect($readings)->toHaveCount(1)->toHaveKey('GOOD');
});

test('battery level is clamped to 0..100', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 0,
            'data' => [
                ['mac' => 'A', 'temperature' => 0.0, 'power' => 250, 'time' => 1747400000000],
                ['mac' => 'B', 'temperature' => 0.0, 'power' => -5, 'time' => 1747400000000],
            ],
        ], 200),
    ]);

    $readings = makeJaaleeClient()->fetchAllReadings();

    expect($readings['A']->batteryLevel)->toBe(100)
        ->and($readings['B']->batteryLevel)->toBe(0);
});

test('throws SensorApiException with a helpful message on code 3 (invalid token)', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 3,
            'message' => 'invalid token',
            'data' => null,
        ], 200),
    ]);

    expect(fn () => makeJaaleeClient()->fetchAllReadings())
        ->toThrow(SensorApiException::class, 'token is invalid');
});

test('throws SensorApiException on non-zero code other than 3', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 7,
            'message' => 'rate limited',
            'data' => null,
        ], 200),
    ]);

    expect(fn () => makeJaaleeClient()->fetchAllReadings())
        ->toThrow(SensorApiException::class, 'code 7');
});

test('throws SensorApiException on HTTP error responses', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response('upstream boom', 500),
    ]);

    expect(fn () => makeJaaleeClient()->fetchAllReadings())
        ->toThrow(SensorApiException::class);
});

test('treats an empty data array as zero readings (not an error)', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response(['code' => 0, 'data' => []], 200),
    ]);

    expect(makeJaaleeClient()->fetchAllReadings())->toBe([]);
});
