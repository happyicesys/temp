<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll every active device once a minute. Jaalee's Open API rate-limits at
// ~1 request per device per minute, so this cadence is the maximum the
// upstream will permit. `withoutOverlapping` guards against a slow tick
// stacking on the next one when many devices are configured.
Schedule::command('sensors:poll')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
