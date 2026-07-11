<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'jaalee' => [
        // Jaalee's Open API root. The /v1 prefix lives on the endpoint paths
        // (so paths look like /v1/open/data/all). Trailing slashes are
        // stripped by the client.
        'base_url' => env('JAALEE_BASE_URL', 'https://sensor.jaalee.com'),

        // Obtained once via POST /v1/open/login and is permanently valid
        // until you log in again. Stored in .env.
        'token' => env('JAALEE_API_TOKEN'),

        'timeout' => (int) env('JAALEE_TIMEOUT_SECONDS', 10),
    ],

];
