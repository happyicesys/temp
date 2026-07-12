<?php

namespace App\Console\Commands;

use App\Jobs\PollVendorAccountJob;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Fan-out command run by the scheduler every minute. Dispatches one
 * {@see PollVendorAccountJob} per distinct vendor that has at least one
 * active device configured.
 *
 * Vendor-scoped (not device-scoped) because real IoT APIs rate-limit per
 * account, and most expose a single endpoint that returns every device
 * the account owns in one call. Letting the scheduler fan out per-device
 * would just torch that rate limit.
 */
class PollAllDevicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sensors:poll
        {--vendor= : Restrict to a single vendor (e.g. --vendor=jaalee)}
        {--sync    : Run polls inline instead of dispatching to the queue (useful for debugging)}
        {--force   : Ignore the per-vendor throttle and poll immediately}';

    /**
     * The console command description.
     */
    protected $description = 'Dispatch one poll job per vendor account that has active devices.';

    public function handle(): int
    {
        $vendor = $this->option('vendor');
        $sync = (bool) $this->option('sync');
        $force = (bool) $this->option('force') || $sync;

        $vendors = Device::query()
            ->where('is_active', true)
            ->when($vendor, fn ($q) => $q->where('vendor', $vendor))
            ->select('vendor')
            ->distinct()
            ->pluck('vendor');

        $dispatched = [];

        foreach ($vendors as $vendorName) {
            if (! $force && ! $this->isDueForPoll($vendorName)) {
                continue;
            }

            if ($sync) {
                PollVendorAccountJob::dispatchSync($vendorName);
            } else {
                PollVendorAccountJob::dispatch($vendorName);
            }

            $this->markPolled($vendorName);
            $dispatched[] = $vendorName;
        }

        if ($dispatched === []) {
            $this->info('No vendors due for polling.');

            return self::SUCCESS;
        }

        $this->info('Dispatched poll job(s) for: '.implode(', ', $dispatched));

        return self::SUCCESS;
    }

    /**
     * Whether enough time has elapsed since this vendor was last polled.
     *
     * Vendor APIs such as Jaalee rate-limit at roughly one request per minute
     * per account, so the scheduler ticks faster than we actually poll and
     * this throttle enforces the real `sensors.poll.min_interval_seconds`
     * cadence per vendor.
     */
    private function isDueForPoll(string $vendor): bool
    {
        $nextAllowedAt = Cache::get($this->throttleKey($vendor));

        return $nextAllowedAt === null || now()->gte(Carbon::parse($nextAllowedAt));
    }

    /**
     * Record that this vendor was just polled, blocking further dispatches
     * until the configured interval has elapsed.
     */
    private function markPolled(string $vendor): void
    {
        $interval = (int) config('sensors.poll.min_interval_seconds');
        $nextAllowedAt = now()->addSeconds($interval);

        Cache::put(
            $this->throttleKey($vendor),
            $nextAllowedAt->toIso8601String(),
            $nextAllowedAt->copy()->addMinutes(5),
        );
    }

    private function throttleKey(string $vendor): string
    {
        return "sensors:poll:next-allowed:{$vendor}";
    }
}
