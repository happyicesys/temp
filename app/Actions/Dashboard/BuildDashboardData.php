<?php

namespace App\Actions\Dashboard;

use App\Models\Device;
use App\Models\Temp;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Assembles every figure the dashboard renders from live {@see Temp} data.
 *
 * Each device's most recent temperature sample is treated as its headline
 * figure. All bucketing is done in PHP rather than via database date functions
 * so the same logic runs identically on the production MySQL connection and the
 * in-memory SQLite connection used by the test suite.
 */
class BuildDashboardData
{
    /**
     * A device is considered "reporting now" only if its most recent reading
     * landed within this many minutes of the moment we render.
     */
    private const ONLINE_WITHIN_MINUTES = 10;

    /**
     * How many devices the "Devices" side panel surfaces at once.
     */
    private const DEVICE_ROW_LIMIT = 8;

    /**
     * Build the full dashboard payload for a range.
     *
     * @return array{
     *     range: string,
     *     stats: array<int, array<string, mixed>>,
     *     chart: array<string, mixed>,
     *     devices: array<int, array<string, mixed>>,
     * }
     */
    public function handle(DashboardRange $range, ?CarbonInterface $now = null): array
    {
        $to = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $from = $range->startsAt($to);
        $onlineSince = $to->subMinutes(self::ONLINE_WITHIN_MINUTES);

        $devices = Device::query()
            ->with('latestTemp')
            ->orderBy('name')
            ->get();

        $rows = $devices->map(fn (Device $device): array => $this->deviceRow($device, $onlineSince));

        return [
            'range' => $range->value,
            'stats' => $this->stats($rows, $to),
            'chart' => $this->chart($range, $from, $to),
            'devices' => $rows
                ->sortBy([
                    fn (array $row): int => $this->statusPriority($row['status']),
                    fn (array $row): string => strtolower((string) $row['name']),
                ])
                ->take(self::DEVICE_ROW_LIMIT)
                ->values()
                ->all(),
        ];
    }

    /**
     * Shape a single device into a status row.
     *
     * @return array{id: int, name: string, location: ?string, value: ?float, status: string, recordedAt: ?string}
     */
    private function deviceRow(Device $device, CarbonInterface $onlineSince): array
    {
        $reading = $device->latestTemp;
        $value = $reading?->temperature !== null ? (float) $reading->temperature : null;
        $isOnline = $reading !== null
            && $value !== null
            && $reading->recorded_at !== null
            && $reading->recorded_at->greaterThanOrEqualTo($onlineSince);

        return [
            'id' => $device->id,
            'name' => $device->name,
            'location' => $device->location,
            'value' => $value,
            'status' => match (true) {
                ! $isOnline => 'offline',
                $this->isBreaching($device, $value) => 'warn',
                default => 'ok',
            },
            'recordedAt' => $reading?->recorded_at?->diffForHumans(),
        ];
    }

    /**
     * Whether a reading falls outside the device's configured alert band. A
     * threshold that is not set never contributes to a breach.
     */
    private function isBreaching(Device $device, ?float $value): bool
    {
        if ($value === null) {
            return false;
        }

        $low = $device->alert_low_temp;
        $high = $device->alert_high_temp;

        return ($low !== null && $value < (float) $low)
            || ($high !== null && $value > (float) $high);
    }

    /**
     * The four headline stat cards.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function stats(Collection $rows, CarbonInterface $to): array
    {
        $total = $rows->count();
        $online = $rows->where('status', '!=', 'offline')->count();
        $breaching = $rows->where('status', 'warn')->count();
        $onlinePercent = $total > 0 ? (int) round($online / $total * 100) : 0;

        [$currentAvg, $previousAvg] = $this->hourlyAverages($to);
        $avgDelta = ($currentAvg !== null && $previousAvg !== null)
            ? round($currentAvg - $previousAvg, 1)
            : null;

        return [
            [
                'key' => 'devices',
                'label' => 'Devices',
                'value' => (string) $total,
                'sub' => 'monitored',
                'delta' => '',
                'trend' => 'flat',
                'tone' => 'neutral',
            ],
            [
                'key' => 'active',
                'label' => 'Reporting now',
                'value' => (string) $online,
                'sub' => 'online',
                'delta' => $total > 0 ? "{$onlinePercent}%" : '',
                'trend' => 'flat',
                'tone' => 'good',
            ],
            [
                'key' => 'alerts',
                'label' => 'Active alerts',
                'value' => (string) $breaching,
                'sub' => 'breaching',
                'delta' => '',
                'trend' => 'flat',
                'tone' => $breaching > 0 ? 'warn' : 'neutral',
            ],
            [
                'key' => 'avg',
                'label' => 'Avg temp',
                'value' => $currentAvg !== null ? sprintf('%.1f°', $currentAvg) : '—',
                'sub' => 'last hour',
                'delta' => $avgDelta !== null ? sprintf('%+.1f°', $avgDelta) : '',
                'trend' => $this->trend($avgDelta),
                'tone' => 'neutral',
            ],
        ];
    }

    /**
     * Fleet-wide temperature average for the last hour and the hour before it.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function hourlyAverages(CarbonInterface $to): array
    {
        $twoHoursAgo = CarbonImmutable::instance($to)->subHours(2);
        $oneHourAgo = CarbonImmutable::instance($to)->subHour();

        $readings = $this->temperatureReadings($twoHoursAgo, $to);

        $average = fn (Collection $group): ?float => $group->isEmpty()
            ? null
            : round($group->avg(fn (Temp $temp): float => (float) $temp->temperature), 1);

        $withValue = $readings->filter(fn (Temp $temp): bool => $temp->temperature !== null);

        return [
            $average($withValue->filter(fn (Temp $t): bool => $t->recorded_at->greaterThanOrEqualTo($oneHourAgo))),
            $average($withValue->filter(fn (Temp $t): bool => $t->recorded_at->lessThan($oneHourAgo))),
        ];
    }

    /**
     * The temperature trend chart: a fleet average per time bucket, plus the
     * headline figure and its move against the previous bucket.
     *
     * @return array{series: array<int, float>, current: ?string, unit: string, delta: ?string, trend: string, axisLabels: array<int, string>}
     */
    private function chart(DashboardRange $range, CarbonInterface $from, CarbonInterface $to): array
    {
        $series = $this->fleetSeries($range, $from, $to);

        $current = $series !== [] ? end($series) : null;
        $previous = count($series) >= 2 ? $series[count($series) - 2] : null;
        $delta = ($current !== null && $previous !== null) ? round($current - $previous, 1) : null;

        return [
            'series' => $series,
            'current' => $current !== null ? sprintf('%.1f', $current) : null,
            'unit' => '°C',
            'delta' => $delta !== null ? sprintf('%.1f', abs($delta)) : null,
            'trend' => $this->trend($delta),
            'axisLabels' => $range->axisLabels($from, $to),
        ];
    }

    /**
     * Bucket every reading in the window into a fleet average per slot, carrying
     * the last known value across empty buckets so the line stays continuous.
     * Returns an empty array when there is no data to plot.
     *
     * @return array<int, float>
     */
    private function fleetSeries(DashboardRange $range, CarbonInterface $from, CarbonInterface $to): array
    {
        $bucketCount = $range->buckets();
        $bucketSeconds = $range->bucketMinutes() * 60;

        /** @var array<int, array{sum: float, count: int}> $buckets */
        $buckets = array_fill(0, $bucketCount, ['sum' => 0.0, 'count' => 0]);

        foreach ($this->temperatureReadings($from, $to) as $reading) {
            if ($reading->temperature === null) {
                continue;
            }

            $offset = (int) floor($reading->recorded_at->diffInSeconds($from, true) / $bucketSeconds);
            $index = max(0, min($bucketCount - 1, $offset));

            $buckets[$index]['sum'] += (float) $reading->temperature;
            $buckets[$index]['count']++;
        }

        $averages = array_map(
            fn (array $bucket): ?float => $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 1) : null,
            $buckets,
        );

        return $this->fillGaps($averages);
    }

    /**
     * Replace null buckets with the nearest known reading: forward-fill for
     * interior/trailing gaps, back-fill for a leading gap.
     *
     * @param  array<int, ?float>  $values
     * @return array<int, float>
     */
    private function fillGaps(array $values): array
    {
        if (collect($values)->every(fn (?float $value): bool => $value === null)) {
            return [];
        }

        $filled = [];
        $last = null;

        foreach ($values as $value) {
            if ($value !== null) {
                $last = $value;
            }
            $filled[] = $last;
        }

        // Back-fill any leading nulls with the first value we ever saw.
        $firstKnown = collect($filled)->first(fn (?float $value): bool => $value !== null);

        return array_map(fn (?float $value): float => $value ?? $firstKnown, $filled);
    }

    /**
     * Fetch temperature readings within a window, ordered oldest-first.
     *
     * @return Collection<int, Temp>
     */
    private function temperatureReadings(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Temp::query()
            ->between($from, $to)
            ->orderBy('recorded_at')
            ->get(['id', 'device_id', 'temperature', 'recorded_at']);
    }

    /**
     * Map a signed delta onto the up/down/flat vocabulary the cards understand.
     */
    private function trend(?float $delta): string
    {
        return match (true) {
            $delta === null || abs($delta) < 0.05 => 'flat',
            $delta < 0 => 'down',
            default => 'up',
        };
    }

    /**
     * Sort order for the device panel: alerts first, then offline, then healthy.
     */
    private function statusPriority(string $status): int
    {
        return match ($status) {
            'warn' => 0,
            'offline' => 1,
            default => 2,
        };
    }
}
