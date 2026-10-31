<?php

namespace App\Services;

use App\Services\Clients\BaseClient;
use App\Services\Clients\IdentityClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class AuthSession
{
    public const EXPIRES_AT = 'jwt_expires_at';

    public const USER = 'user';

    private const REFRESH_WITHIN_MINUTES = 10;

    public function __construct(private IdentityClient $identity) {}

    public function start(array $auth): void
    {
        session()->regenerate();

        $this->store($auth);
    }

    public function store(array $auth): void
    {
        session([
            BaseClient::SESSION_TOKEN => $auth['token'],
            self::EXPIRES_AT => $auth['expires_at'],
            self::USER => Arr::only($auth['user'], ['id', 'name', 'role', 'avatar']),
        ]);
    }

    public function check(): bool
    {
        return session()->has(self::USER) && $this->expiresAt()?->isFuture() === true;
    }

    public function user(): ?array
    {
        return $this->check() ? session(self::USER) : null;
    }

    public function expiresSoon(): bool
    {
        return $this->expiresAt()?->subMinutes(self::REFRESH_WITHIN_MINUTES)->isPast() ?? true;
    }

    public function refresh(): void
    {
        $this->store($this->identity->refresh());
    }

    public function end(): void
    {
        session()->invalidate();
        session()->regenerateToken();
    }

    private function expiresAt(): ?CarbonImmutable
    {
        $expiresAt = session(self::EXPIRES_AT);

        return $expiresAt ? CarbonImmutable::parse($expiresAt) : null;
    }
}
