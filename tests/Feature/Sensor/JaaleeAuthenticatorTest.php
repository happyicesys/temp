<?php

use App\Services\Sensor\Jaalee\JaaleeAuthenticator;
use App\Services\Sensor\SensorApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function makeJaaleeAuth(): JaaleeAuthenticator
{
    return new JaaleeAuthenticator(
        http: app(HttpFactory::class),
        baseUrl: 'https://sensor.jaalee.com',
        timeoutSeconds: 5,
    );
}

test('requestCode calls /v1/open/code with the account query param', function () {
    Http::fake([
        'sensor.jaalee.com/v1/open/code*' => Http::response(['code' => 0, 'message' => '', 'data' => []], 200),
    ]);

    makeJaaleeAuth()->requestCode('leehongjie91@gmail.com');

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/v1/open/code')
            && $request->data() === ['account' => 'leehongjie91@gmail.com'];
    });
});

test('requestCode throws when Jaalee returns a non-zero code', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response(['code' => 9, 'message' => 'account not found'], 200),
    ]);

    expect(fn () => makeJaaleeAuth()->requestCode('nobody@example.com'))
        ->toThrow(SensorApiException::class, 'code 9');
});

test('requestCode throws on HTTP errors', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response('boom', 500),
    ]);

    expect(fn () => makeJaaleeAuth()->requestCode('a@b.com'))
        ->toThrow(SensorApiException::class);
});

test('login POSTs account, code and timeZone and returns the token', function () {
    Http::fake([
        'sensor.jaalee.com/v1/open/login' => Http::response([
            'code' => 0,
            'message' => '',
            'data' => ['token' => 'PERM-TOKEN-XYZ'],
        ], 200),
    ]);

    $token = makeJaaleeAuth()->login('leehongjie91@gmail.com', '1234', 'Asia/Singapore');

    expect($token)->toBe('PERM-TOKEN-XYZ');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/open/login')
            && $request->data() === [
                'account' => 'leehongjie91@gmail.com',
                'code' => '1234',
                'timeZone' => 'Asia/Singapore',
            ];
    });
});

test('login throws when Jaalee returns a non-zero code (wrong code)', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response([
            'code' => 1,
            'message' => 'verification code incorrect',
        ], 200),
    ]);

    expect(fn () => makeJaaleeAuth()->login('a@b.com', '0000', 'Asia/Singapore'))
        ->toThrow(SensorApiException::class, 'code 1');
});

test('login throws when token is missing from a "success" payload', function () {
    Http::fake([
        'sensor.jaalee.com/*' => Http::response(['code' => 0, 'data' => []], 200),
    ]);

    expect(fn () => makeJaaleeAuth()->login('a@b.com', '1234', 'Asia/Singapore'))
        ->toThrow(SensorApiException::class, 'no token');
});
