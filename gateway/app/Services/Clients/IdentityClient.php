<?php

namespace App\Services\Clients;

class IdentityClient extends BaseClient
{
    protected string $service = 'identity';

    public function register(array $data): array
    {
        return $this->post('/api/register', $data)['data'];
    }

    public function login(string $email, string $password): array
    {
        return $this->post('/api/login', ['email' => $email, 'password' => $password])['data'];
    }

    public function refresh(): array
    {
        return $this->post('/api/token/refresh')['data'];
    }
}
