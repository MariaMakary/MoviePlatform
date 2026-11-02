<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'sandbox.smtp.mailtrap.io'),
            'port' => (int) env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => secret('mail_password'),
            'timeout' => 5,
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@movieplatform.test'),
        'name' => env('MAIL_FROM_NAME', 'MoviePlatform'),
    ],

    // The reset link in the email points to this Gateway page.
    'gateway_url' => rtrim(env('GATEWAY_URL', 'http://localhost:8000'), '/'),
];
