<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic Device Registration
    |--------------------------------------------------------------------------
    |
    | When true, the poll job creates a Device row on the fly for any device
    | the vendor returns that we don't yet have locally, then immediately
    | starts logging its readings. When false, unknown devices are logged and
    | skipped (they must be registered manually via the CRUD UI first).
    |
    */

    'auto_register' => (bool) env('SENSORS_AUTO_REGISTER', true),

    /*
    |--------------------------------------------------------------------------
    | Default Customer for Auto-Registered Devices
    |--------------------------------------------------------------------------
    |
    | Devices require a customer. Auto-registered devices are attached to this
    | placeholder customer (created on demand) so an operator can reassign
    | them later from the UI without the poll ever failing on a missing owner.
    |
    */

    'default_customer' => [
        'code' => env('SENSORS_DEFAULT_CUSTOMER_CODE', 'UNASSIGNED'),
        'name' => env('SENSORS_DEFAULT_CUSTOMER_NAME', 'Unassigned'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Poll Cadence
    |--------------------------------------------------------------------------
    |
    | Minimum number of seconds that must elapse between two poll dispatches
    | for the same vendor account. Vendor APIs such as Jaalee rate-limit at
    | roughly one request per minute per account, so we space dispatches a
    | little beyond that window (70s by default) to leave headroom for clock
    | drift and avoid tripping the upstream limiter. The scheduler ticks more
    | frequently than this; the throttle in PollAllDevicesCommand enforces the
    | real interval.
    |
    */

    'poll' => [
        'min_interval_seconds' => (int) env('SENSORS_POLL_MIN_INTERVAL_SECONDS', 70),
    ],

];
