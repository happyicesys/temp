<?php

use App\Actions\Dashboard\DashboardRange;
use Carbon\CarbonImmutable;

test('an absent or unknown value falls back to the 24h window', function () {
    expect(DashboardRange::fromRequest(null))->toBe(DashboardRange::Day)
        ->and(DashboardRange::fromRequest('nope'))->toBe(DashboardRange::Day);
});

test('a known value resolves to its case', function (string $value, DashboardRange $expected) {
    expect(DashboardRange::fromRequest($value))->toBe($expected);
})->with([
    ['24h', DashboardRange::Day],
    ['7d', DashboardRange::Week],
    ['30d', DashboardRange::Month],
]);

test('each range describes a matching bucket layout', function (DashboardRange $range, int $buckets, int $minutes, int $window) {
    expect($range->buckets())->toBe($buckets)
        ->and($range->bucketMinutes())->toBe($minutes)
        ->and($range->windowMinutes())->toBe($window);
})->with([
    [DashboardRange::Day, 24, 60, 24 * 60],
    [DashboardRange::Week, 7, 1_440, 7 * 1_440],
    [DashboardRange::Month, 30, 1_440, 30 * 1_440],
]);

test('the window starts one full span before the given end', function () {
    $to = CarbonImmutable::parse('2026-07-12 12:00:00');

    expect(DashboardRange::Day->startsAt($to)->toDateTimeString())
        ->toBe('2026-07-11 12:00:00');
});

test('axis labels line up with the range', function () {
    $to = CarbonImmutable::parse('2026-07-12 12:00:00');

    expect(DashboardRange::Day->axisLabels(DashboardRange::Day->startsAt($to), $to))->toHaveCount(5)
        ->and(DashboardRange::Week->axisLabels(DashboardRange::Week->startsAt($to), $to))->toHaveCount(7)
        ->and(DashboardRange::Month->axisLabels(DashboardRange::Month->startsAt($to), $to))->toHaveCount(5);
});
