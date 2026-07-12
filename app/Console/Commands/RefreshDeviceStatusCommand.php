<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles each active device's persisted online/offline state with the
 * freshness of its most recent reading, run once a minute by the scheduler.
 *
 * The device list already derives the live badge from `last_reading_at`, so
 * this command exists for the *transition*: it records when a device drops
 * offline (and clears it when the device recovers), giving a single, debounced
 * hook point for offline alerts without re-notifying every tick. Inactive
 * devices are intentionally skipped — a paused device is expected to be silent.
 */
class RefreshDeviceStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'devices:refresh-status';

    /**
     * The console command description.
     */
    protected $description = 'Mark active devices online or offline based on how recently they last reported.';

    public function handle(): int
    {
        $wentOffline = 0;
        $cameOnline = 0;

        Device::query()
            ->where('is_active', true)
            ->each(function (Device $device) use (&$wentOffline, &$cameOnline): void {
                $isOnline = $device->hasFreshReading();

                if ($isOnline === $device->is_online) {
                    return;
                }

                if ($isOnline) {
                    $device->forceFill([
                        'is_online' => true,
                        'went_offline_at' => null,
                    ])->save();

                    $cameOnline++;

                    return;
                }

                $device->forceFill([
                    'is_online' => false,
                    'went_offline_at' => now(),
                ])->save();

                $wentOffline++;

                // Alert hook: a device just crossed from online to offline. Wire
                // an offline notification to the device's alert_emails here.
                Log::warning('Device went offline', [
                    'device_id' => $device->id,
                    'name' => $device->name,
                    'last_reading_at' => $device->last_reading_at?->toIso8601String(),
                ]);
            });

        $this->info("Device status refreshed: {$wentOffline} went offline, {$cameOnline} came online.");

        return self::SUCCESS;
    }
}
