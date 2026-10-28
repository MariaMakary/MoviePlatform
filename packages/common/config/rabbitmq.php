<?php

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => secret('rabbitmq_user', ''),
    'password' => secret('rabbitmq_password', ''),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => 'movieplatform.events',
];
