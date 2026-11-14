<?php

return [
    'service_token' => secret('service_token', ''),

    'timeout' => 3,

    'services' => [
        'identity' => env('IDENTITY_URL', 'http://127.0.0.1:8001'),
        'catalog' => env('CATALOG_URL', 'http://127.0.0.1:8002'),
        'engagement' => env('ENGAGEMENT_URL', 'http://127.0.0.1:8003'),
        'social' => env('SOCIAL_URL', 'http://127.0.0.1:8004'),
        'intelligence' => env('INTELLIGENCE_URL', 'http://127.0.0.1:8005'),
        'notification' => env('NOTIFICATION_URL', 'http://127.0.0.1:8006'),
    ],
];
