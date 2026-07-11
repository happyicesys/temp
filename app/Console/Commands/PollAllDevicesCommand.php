<?php

namespace App\Console\Commands;

use App\Jobs\PollVendorAccountJob;
use App\Models\Device;
use Illuminate\Console\Command;

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
        {--sync   : Run polls inline instead of dispatching to the queue (useful for debugging)}';

    /**
     * The console command description.
     */
    protected $description = 'Dispatch one poll job per vendor account that has active devices.';

    public function handle(): int
    {
        $vendor = $this->option('vendor');
        $sync = (bool) $this->option('sync');

        $vendors = Device::query()
            ->where('is_active', true)
            ->when($vendor, fn ($q) => $q->where('vendor', $vendor))
            ->select('vendor')
            ->distinct()
            ->pluck('vendor');

        foreach ($vendors as $vendorName) {
            if ($sync) {
                PollVendorAccountJob::dispatchSync($vendorName);
            } else {
                PollVendorAccountJob::dispatch($vendorName);
            }
        }

        $this->info("Dispatched poll job(s) for: {$vendors->implode(', ')}");

        return self::SUCCESS;
    }
}
