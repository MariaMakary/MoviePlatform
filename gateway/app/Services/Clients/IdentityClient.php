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

    /** Public profiles (id, name, avatar…) for up to 100 ids, from task 1145; unknown ids are skipped. */
    public function users(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->get('/api/users', ['ids' => implode(',', array_values(array_unique($ids)))])['data'];
    }

    public function refresh(): array
    {
        return $this->post('/api/token/refresh')['data'];
    }

    /** Identity answers the same for every email, so nobody can tell which accounts exist. */
    public function forgotPassword(string $email): void
    {
        $this->post('/api/password/forgot', ['email' => $email]);
    }

    public function resetPassword(array $data): void
    {
        $this->post('/api/password/reset', $data);
    }
}
