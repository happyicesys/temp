<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.jaalee.base_url', 'https://sensor.jaalee.com');
    config()->set('services.jaalee.timeout', 5);
    config()->set('app.timezone', 'Asia/Singapore');

    // Use a sandboxed .env path so the test never writes to the real one.
    $this->envPath = tempnam(sys_get_temp_dir(), 'env_');
    file_put_contents(
        $this->envPath,
        "APP_NAME=Testing\nJAALEE_API_TOKEN=old-token-value\nOTHER_KEY=keep-me\n",
    );

    $app = $this->app;
    $original = $app->environmentFilePath();
    $envDir = dirname($this->envPath);
    $envFile = basename($this->envPath);
    $app->useEnvironmentPath($envDir);
    $app->loadEnvironmentFrom($envFile);

    $this->beforeApplicationDestroyed(function () use ($app, $original) {
        @unlink($this->envPath);
        $app->useEnvironmentPath(dirname($original));
        $app->loadEnvironmentFrom(basename($original));
    });
});

function fakeJaaleeAuthEndpoints(string $token = 'NEW-TOKEN-123'): void
{
    Http::fake([
        'sensor.jaalee.com/v1/open/code*' => Http::response(['code' => 0, 'data' => []], 200),
        'sensor.jaalee.com/v1/open/login' => Http::response([
            'code' => 0,
            'data' => ['token' => $token],
        ], 200),
    ]);
}

test('happy path: requests code, accepts entry, prints token, updates .env', function () {
    fakeJaaleeAuthEndpoints('NEW-TOKEN-123');

    $this->artisan('sensors:jaalee-login', ['account' => 'leehongjie91@gmail.com'])
        ->expectsQuestion('Enter the verification code', '1234')
        ->expectsConfirmation('Save this token to .env as JAALEE_API_TOKEN?', 'yes')
        ->expectsOutputToContain('NEW-TOKEN-123')
        ->assertSuccessful();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/open/code') && $r->data() === ['account' => 'leehongjie91@gmail.com']);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/open/login') && $r['code'] === '1234' && $r['account'] === 'leehongjie91@gmail.com' && $r['timeZone'] === 'Asia/Singapore');

    $contents = file_get_contents($this->envPath);
    expect($contents)
        ->toContain('JAALEE_API_TOKEN="NEW-TOKEN-123"')
        ->not->toContain('old-token-value')
        ->toContain('OTHER_KEY=keep-me');
});

test('--no-write keeps .env untouched but still prints the token', function () {
    fakeJaaleeAuthEndpoints('UNTOUCHED-FLOW-TOKEN');

    $this->artisan('sensors:jaalee-login', [
        'account' => 'leehongjie91@gmail.com',
        '--no-write' => true,
    ])
        ->expectsQuestion('Enter the verification code', '1234')
        ->expectsOutputToContain('UNTOUCHED-FLOW-TOKEN')
        ->assertSuccessful();

    expect(file_get_contents($this->envPath))->toContain('JAALEE_API_TOKEN=old-token-value');
});

test('declining the save prompt leaves .env untouched', function () {
    fakeJaaleeAuthEndpoints('NEW-TOKEN-DECLINED');

    $this->artisan('sensors:jaalee-login', ['account' => 'leehongjie91@gmail.com'])
        ->expectsQuestion('Enter the verification code', '1234')
        ->expectsConfirmation('Save this token to .env as JAALEE_API_TOKEN?', 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->envPath))->toContain('JAALEE_API_TOKEN=old-token-value');
});

test('--timezone overrides the configured timezone', function () {
    fakeJaaleeAuthEndpoints();

    $this->artisan('sensors:jaalee-login', [
        'account' => 'leehongjie91@gmail.com',
        '--timezone' => 'GMT+08:00',
    ])
        ->expectsQuestion('Enter the verification code', '1234')
        ->expectsConfirmation('Save this token to .env as JAALEE_API_TOKEN?', 'no')
        ->assertSuccessful();

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/open/login') && $r['timeZone'] === 'GMT+08:00');
});

test('appends JAALEE_API_TOKEN when missing from .env', function () {
    file_put_contents($this->envPath, "APP_NAME=Testing\nOTHER_KEY=keep-me\n");

    fakeJaaleeAuthEndpoints('APPENDED-TOKEN');

    $this->artisan('sensors:jaalee-login', ['account' => 'leehongjie91@gmail.com'])
        ->expectsQuestion('Enter the verification code', '1234')
        ->expectsConfirmation('Save this token to .env as JAALEE_API_TOKEN?', 'yes')
        ->assertSuccessful();

    expect(file_get_contents($this->envPath))
        ->toContain('JAALEE_API_TOKEN="APPENDED-TOKEN"')
        ->toContain('OTHER_KEY=keep-me');
});

test('fails gracefully when Jaalee rejects the verification code', function () {
    Http::fake([
        'sensor.jaalee.com/v1/open/code*' => Http::response(['code' => 0, 'data' => []], 200),
        'sensor.jaalee.com/v1/open/login' => Http::response(['code' => 1, 'message' => 'code wrong'], 200),
    ]);

    $this->artisan('sensors:jaalee-login', ['account' => 'leehongjie91@gmail.com'])
        ->expectsQuestion('Enter the verification code', '9999')
        ->expectsOutputToContain('Login failed')
        ->assertFailed();

    // The bad attempt must not corrupt .env.
    expect(file_get_contents($this->envPath))->toContain('JAALEE_API_TOKEN=old-token-value');
});

test('fails gracefully when the code-request endpoint errors', function () {
    Http::fake([
        'sensor.jaalee.com/v1/open/code*' => Http::response(['code' => 5, 'message' => 'too many requests'], 200),
    ]);

    $this->artisan('sensors:jaalee-login', ['account' => 'leehongjie91@gmail.com'])
        ->expectsOutputToContain('Failed to request verification code')
        ->assertFailed();
});
