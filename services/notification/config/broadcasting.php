<?php

/*
 * Real-time events go to Reverb, which runs from the Gateway (php artisan reverb:start, port 8080).
 * Illuminate 10 has no reverb broadcaster, but Reverb speaks the Pusher protocol, so the pusher driver
 * is pointed at it. App id and key are the Gateway's; the secret is secrets/reverb_app_secret.
 */

return [

    'default' => env('BROADCAST_DRIVER', 'pusher'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('REVERB_APP_KEY'),
            'secret' => secret('reverb_app_secret'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                'timeout' => 3,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
