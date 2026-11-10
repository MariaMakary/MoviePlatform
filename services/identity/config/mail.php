<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp-relay.brevo.com'),
            'port' => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => secret('mail_password'),
            'timeout' => 10,
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'kaiateam.noreply@gmail.com'),
        'name' => env('MAIL_FROM_NAME', 'KAIA'),
    ],

    'gateway_url' => rtrim(env('GATEWAY_URL', 'http://localhost:8000'), '/'),
];
