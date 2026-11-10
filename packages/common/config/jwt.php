<?php

return [
    'public_key_path' => base_path(env('JWT_PUBLIC_KEY_PATH', '../../keys/jwt_public.pem')),
    'leeway' => (int) env('JWT_LEEWAY', 30),
];
