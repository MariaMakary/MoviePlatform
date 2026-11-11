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

    public function searchUsers(string $query): array
    {
        return $this->get('/api/users/search', ['q' => $query])['data'];
    }

    public function refresh(): array
    {
        return $this->post('/api/token/refresh')['data'];
    }

    public function me(): array
    {
        return $this->get('/api/me')['data'];
    }

    public function updateProfile(string $name, ?string $bio): array
    {
        return $this->put('/api/me', ['name' => $name, 'bio' => $bio])['data'];
    }

    /** Replaces the favorite genres (Catalog genre ids, at most 20; [] clears them). Returns the user with `genre_ids`. */
    public function updateGenres(array $genreIds): array
    {
        return $this->put('/api/me/genres', ['genre_ids' => array_values($genreIds)])['data'];
    }

    public function uploadAvatar(string $contents, string $fileName): array
    {
        return $this->upload('/api/me/avatar', 'avatar', $contents, $fileName)['data'];
    }

    public function changePassword(array $data): void
    {
        $this->put('/api/me/password', $data);
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
