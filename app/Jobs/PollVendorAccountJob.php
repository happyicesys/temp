<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Temp;
use App\Services\Sensor\DeviceProvisioner;
use App\Services\Sensor\SensorApiException;
use App\Services\Sensor\SensorReading;
use App\Services\Sensor\SensorVendorClientResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Polls one vendor account once and persists a Temp row for every known
 * device that came back in the response.
 *
 * The job is account-scoped (not device-scoped) because Jaalee's Open API
 * rate-limits at 1 request per minute per account and exposes a single
 * endpoint that returns every device the account owns. Fanning out
 * per-device would burn through the rate limit immediately.
 *
 * Devices the upstream returns but that have no matching local row are
 * auto-registered via {@see DeviceProvisioner} when `sensors.auto_register`
 * is on (the default), so a newly paired sensor starts logging on the very
 * next tick with zero manual setup. When auto-registration is off they are
 * logged at info level and skipped instead. Known devices that did *not* come
 * back in this poll are simply left alone — we never invent readings.
 *
 * The job is {@see ShouldBeUnique} keyed by vendor: the scheduler ticks far
 * faster than the upstream rate limit allows, so without this a slow poll would
 * let identical jobs stack up and each fire a duplicate request the moment a
 * worker frees up — tripping Jaalee's limiter and flooding the log. Only one
 * poll per vendor may be queued or running at any instant.
 */
class PollVendorAccountJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Polling is short by design — fail fast on a stuck worker.
     */
    public int $timeout = 30;

    /**
     * Don't retry. The scheduler ticks again every minute and the upstream
     * rate limit means a retry would only fail.
     */
    public int $tries = 1;

    /**
     * Failsafe ceiling on the uniqueness lock. In the happy path the lock is
     * released the instant the job finishes; this only matters if a worker is
     * killed mid-poll without releasing it. Comfortably longer than
     * {@see $timeout} so a legitimately slow poll never drops its own lock.
     */
    public int $uniqueFor = 120;

    public function __construct(
        public readonly string $vendor,
    ) {}

    /**
     * One in-flight poll per vendor account.
     */
    public function uniqueId(): string
    {
        return $this->vendor;
    }

    public function handle(SensorVendorClientResolver $resolver, DeviceProvisioner $provisioner): void
    {
        $now = now();

        try {
            $client = $resolver->forVendor($this->vendor);
            $readings = $client->fetchAllReadings();
        } catch (SensorApiException $e) {
            Log::warning('Sensor account poll failed', [
                'vendor' => $this->vendor,
                'reason' => $e->getMessage(),
            ]);

            // Still touch last_polled_at so the dashboard reflects that
            // we tried.
            Device::query()
                ->where('vendor', $this->vendor)
                ->where('is_active', true)
                ->update(['last_polled_at' => $now]);

            return;
        }

        if ($readings === []) {
            Log::info('Sensor account poll returned no devices', ['vendor' => $this->vendor]);

            return;
        }

        $devices = Device::query()
            ->where('vendor', $this->vendor)
            ->whereIn('vendor_device_id', array_keys($readings))
            ->get()
            ->keyBy('vendor_device_id');

        foreach ($readings as $vendorDeviceId => $reading) {
            $device = $devices->get($vendorDeviceId);

            if ($device === null) {
                if (! $provisioner->isEnabled()) {
                    Log::info('Sensor poll: unknown device returned by vendor', [
                        'vendor' => $this->vendor,
                        'vendor_device_id' => $vendorDeviceId,
                    ]);

                    continue;
                }

                $device = $provisioner->provision($this->vendor, $reading);
                $devices->put($vendorDeviceId, $device);

                Log::info('Sensor poll: auto-registered new device', [
                    'vendor' => $this->vendor,
                    'vendor_device_id' => $vendorDeviceId,
                    'device_id' => $device->id,
                ]);
            }

            $this->persistReading($device, $reading, $now);
        }

        // Devices we polled but that didn't come back in the response still
        // get last_polled_at touched so we can spot silent failures.
        $touched = $devices->pluck('id')->all();
        Device::query()
            ->where('vendor', $this->vendor)
            ->where('is_active', true)
            ->when($touched !== [], fn ($q) => $q->whereNotIn('id', $touched))
            ->update(['last_polled_at' => $now]);
    }

    protected function persistReading(Device $device, SensorReading $reading, \DateTimeInterface $now): void
    {
        Temp::query()->updateOrCreate(
            [
                'device_id' => $device->id,
                'recorded_at' => $reading->recordedAt,
            ],
            $reading->toAttributes() + ['device_id' => $device->id],
        );

        $device->forceFill([
            'last_polled_at' => $now,
            'last_reading_at' => $reading->recordedAt,
            'last_temperature' => $reading->temperature,
            'last_humidity' => $reading->humidity,
        ])->save();
    }
}
