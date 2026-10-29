<?php

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'user' => secret('rabbitmq_user', 'guest'),
    'password' => secret('rabbitmq_password', 'guest'),
];
