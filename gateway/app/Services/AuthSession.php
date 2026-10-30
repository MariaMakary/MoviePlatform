<?php

namespace App\Services;

use App\Services\Clients\BaseClient;
use Illuminate\Support\Arr;

class AuthSession
{
    public const EXPIRES_AT = 'jwt_expires_at';

    public const USER = 'user';

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

    public function user(): ?array
    {
        return session(self::USER);
    }
}
