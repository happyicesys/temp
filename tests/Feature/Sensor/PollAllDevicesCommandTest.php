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

test('the scheduler registers sensors:poll on every-minute cadence', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $matching = collect($schedule->events())->first(
        fn ($event) => str_contains($event->command ?? '', 'sensors:poll'),
    );

    expect($matching)->not->toBeNull()
        ->and($matching->expression)->toBe('* * * * *');
});
