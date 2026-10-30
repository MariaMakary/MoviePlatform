<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

final class IssuedToken
{
    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly CarbonImmutable $expiresAt,
    ) {}
}
