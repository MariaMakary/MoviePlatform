<?php

return [
    'private_key' => secret('jwt_private_key'),
    'public_key_path' => base_path(env('JWT_PUBLIC_KEY_PATH', '../../keys/jwt_public.pem')),
    'ttl' => (int) env('JWT_TTL', 1800),
];
