<?php

namespace App\Actions\Dashboard;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The time window a dashboard view is scoped to.
 *
 * Each case owns everything the aggregation layer needs to describe its window:
 * how far back it reaches, how many buckets the chamber-temperature series is
 * split into, and the labels drawn along the chart's x-axis. Keeping this on the
 * enum means the controller and the {@see BuildDashboardData} action never have
 * to branch on the raw "24h" / "7d" / "30d" string.
 */
enum DashboardRange: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';

    /**
     * Resolve a range from an untrusted request value, falling back to the
     * default 24-hour window when it is missing or unrecognised.
     */
    public static function fromRequest(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Day;
    }

    /**
     * The number of points the chamber-temperature series is bucketed into.
     */
    public function buckets(): int
    {
        return match ($this) {
            self::Day => 24,
            self::Week => 7,
            self::Month => 30,
        };
    }

    /**
     * The width of a single bucket, in minutes.
     */
    public function bucketMinutes(): int
    {
        return match ($this) {
            self::Day => 60,
            self::Week, self::Month => 1_440,
        };
    }

    /**
     * The total length of the window, in minutes.
     */
    public function windowMinutes(): int
    {
        return $this->buckets() * $this->bucketMinutes();
    }

    /**
     * The start of the window relative to a given end time.
     */
    public function from(CarbonInterface $to): CarbonImmutable
    {
        return CarbonImmutable::instance($to)->subMinutes($this->windowMinutes());
    }

    /**
     * Human labels for the chart's x-axis. The template renders them evenly
     * spaced, so the count can differ per range.
     *
     * @return array<int, string>
     */
    public function axisLabels(CarbonInterface $from, CarbonInterface $to): array
    {
        return match ($this) {
            self::Day => ['00:00', '06:00', '12:00', '18:00', 'Now'],
            self::Week => $this->dailyLabels($from, 7, 'ddd'),
            self::Month => $this->evenlySpacedLabels($from, $to, 5, 'D MMM'),
        };
    }

    /**
     * One label per day starting at $from (used by the weekly view).
     *
     * @return array<int, string>
     */
    private function dailyLabels(CarbonInterface $from, int $days, string $format): array
    {
        $start = CarbonImmutable::instance($from);

        return collect(range(0, $days - 1))
            ->map(fn (int $offset): string => $start->addDays($offset)->isoFormat($format))
            ->all();
    }

    /**
     * $count labels spread evenly across [$from, $to], with the last one pinned
     * to "Now" so the chart reads left-to-right up to the present.
     *
     * @return array<int, string>
     */
    private function evenlySpacedLabels(CarbonInterface $from, CarbonInterface $to, int $count, string $format): array
    {
        $start = CarbonImmutable::instance($from);
        $end = CarbonImmutable::instance($to);
        $totalSeconds = $end->diffInSeconds($start);

        return collect(range(0, $count - 1))
            ->map(function (int $step) use ($start, $totalSeconds, $count, $format): string {
                if ($step === $count - 1) {
                    return 'Now';
                }

                $offset = (int) round($totalSeconds * ($step / ($count - 1)));

                return $start->addSeconds($offset)->isoFormat($format);
            })
            ->all();
    }
}
