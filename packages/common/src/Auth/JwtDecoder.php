<?php

namespace MoviePlatform\Common\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

class JwtDecoder
{
    public function __construct(
        private readonly string $publicKey,
        private readonly int $leeway = 30,
    ) {}

    public function decode(string $token): JwtUser
    {
        JWT::$leeway = $this->leeway;

        $claims = JWT::decode($token, new Key($this->publicKey, 'RS256'));

        foreach (['sub', 'role', 'name', 'exp'] as $claim) {
            if (! isset($claims->{$claim})) {
                throw new UnexpectedValueException("Missing claim: $claim");
            }
        }

        $role = is_string($claims->role) ? Role::tryFrom($claims->role) : null;

        if (! is_string($claims->sub) || $claims->sub === '' || $role === null) {
            throw new UnexpectedValueException('Invalid sub or role claim');
        }

        return new JwtUser($claims->sub, $role, (string) $claims->name);
    }
}
