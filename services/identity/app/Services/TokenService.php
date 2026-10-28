<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use RuntimeException;

class TokenService
{
    public function issue(User $user): string
    {
        $privateKey = config('jwt.private_key');

        if (! $privateKey) {
            throw new RuntimeException('JWT private key is missing: add it to secrets/jwt_private_key.');
        }

        $now = time();

        return JWT::encode([
            'sub' => (string) $user->id,
            'role' => $user->role ?? 'user',
            'name' => $user->name,
            'iat' => $now,
            'exp' => $now + config('jwt.ttl'),
        ], $privateKey, 'RS256');
    }
}
