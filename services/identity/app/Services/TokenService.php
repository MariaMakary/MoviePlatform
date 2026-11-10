<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use MoviePlatform\Common\Auth\Role;
use RuntimeException;

class TokenService
{
    public function issue(User $user): IssuedToken
    {
        $privateKey = config('jwt.private_key');

        if (! $privateKey) {
            throw new RuntimeException('JWT private key is missing: add it to secrets/jwt_private_key.');
        }

        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds((int) config('jwt.ttl'));

        $token = JWT::encode([
            'sub' => (string) $user->id,
            'role' => ($user->role ?? Role::User)->value,
            'name' => $user->name,
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ], $privateKey, 'RS256');

        return new IssuedToken($user, $token, $expiresAt);
    }
}
