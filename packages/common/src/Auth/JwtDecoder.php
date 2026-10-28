<?php

namespace MoviePlatform\Common\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

class JwtDecoder
{
    private const ROLES = ['user', 'admin'];

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

        if (! is_string($claims->sub) || $claims->sub === '' || ! in_array($claims->role, self::ROLES, true)) {
            throw new UnexpectedValueException('Invalid sub or role claim');
        }

        return new JwtUser($claims->sub, $claims->role, (string) $claims->name);
    }
}
