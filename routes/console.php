<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Poll every active vendor account on a short tick. Jaalee's Open API
// rate-limits at ~1 request per minute per account, so firing exactly on the
// 60s boundary trips the limiter whenever clock drift shortens the gap. We
// tick every 10s and let the per-vendor throttle in `sensors:poll` (see
// `sensors.poll.min_interval_seconds`, 70s by default) enforce the real
// cadence a safe margin beyond the upstream window. `withoutOverlapping`
// guards against a slow tick stacking on the next one.
Schedule::command('sensors:poll')
    ->everyTenSeconds()
    ->withoutOverlapping()
    ->runInBackground();
