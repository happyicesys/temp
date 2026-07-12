<?php

use App\Jobs\PollVendorAccountJob;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;

test('the artisan command dispatches one job per distinct active vendor', function () {
    Bus::fake();

    $customer = Customer::factory()->create();
    Device::factory()->count(2)->for($customer)->create(['vendor' => 'jaalee']);
    Device::factory()->for($customer)->create(['vendor' => 'acme']);
    Device::factory()->for($customer)->inactive()->create(['vendor' => 'shouldignore']);

    $this->artisan('sensors:poll')->assertSuccessful();

    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 2);
    Bus::assertDispatched(PollVendorAccountJob::class, fn (PollVendorAccountJob $j) => $j->vendor === 'jaalee');
    Bus::assertDispatched(PollVendorAccountJob::class, fn (PollVendorAccountJob $j) => $j->vendor === 'acme');
});

test('--vendor option restricts dispatch to a single vendor', function () {
    Bus::fake();

    $customer = Customer::factory()->create();
    Device::factory()->for($customer)->create(['vendor' => 'jaalee']);
    Device::factory()->for($customer)->create(['vendor' => 'acme']);

    $this->artisan('sensors:poll', ['--vendor' => 'jaalee'])->assertSuccessful();

    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 1);
    Bus::assertDispatched(PollVendorAccountJob::class, fn (PollVendorAccountJob $j) => $j->vendor === 'jaalee');
});

test('a vendor is not re-polled until the configured interval elapses', function () {
    Bus::fake();
    config()->set('sensors.poll.min_interval_seconds', 70);

    $customer = Customer::factory()->create();
    Device::factory()->for($customer)->create(['vendor' => 'jaalee']);

    $this->artisan('sensors:poll')->assertSuccessful();
    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 1);

    // A tick partway through the window must not dispatch again.
    $this->travel(30)->seconds();
    $this->artisan('sensors:poll')->assertSuccessful();
    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 1);

    // Once the 70s window has passed, the next tick polls again.
    $this->travel(41)->seconds();
    $this->artisan('sensors:poll')->assertSuccessful();
    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 2);
});

test('the throttle is tracked independently per vendor', function () {
    Bus::fake();
    config()->set('sensors.poll.min_interval_seconds', 70);

    $customer = Customer::factory()->create();
    Device::factory()->for($customer)->create(['vendor' => 'jaalee']);

    // First tick polls jaalee and starts its window.
    $this->artisan('sensors:poll')->assertSuccessful();
    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 1);

    // A new vendor appears mid-window; it is due immediately, jaalee is not.
    Device::factory()->for($customer)->create(['vendor' => 'acme']);
    $this->travel(30)->seconds();
    $this->artisan('sensors:poll')->assertSuccessful();

    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 2);
    Bus::assertDispatched(PollVendorAccountJob::class, fn (PollVendorAccountJob $j) => $j->vendor === 'acme');
});

test('--force bypasses the per-vendor throttle', function () {
    Bus::fake();
    config()->set('sensors.poll.min_interval_seconds', 70);

    $customer = Customer::factory()->create();
    Device::factory()->for($customer)->create(['vendor' => 'jaalee']);

    $this->artisan('sensors:poll')->assertSuccessful();
    $this->artisan('sensors:poll', ['--force' => true])->assertSuccessful();

    Bus::assertDispatchedTimes(PollVendorAccountJob::class, 2);
});

test('the scheduler registers sensors:poll on a sub-minute tick', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $matching = collect($schedule->events())->first(
        fn ($event) => str_contains($event->command ?? '', 'sensors:poll'),
    );

    expect($matching)->not->toBeNull()
        ->and($matching->expression)->toBe('* * * * *')
        ->and($matching->repeatSeconds)->toBe(10);
});
