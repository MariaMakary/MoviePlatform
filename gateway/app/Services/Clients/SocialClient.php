<?php

namespace App\Services\Clients;

class SocialClient extends BaseClient
{
    protected string $service = 'social';

    public function follow(string $userId): array
    {
        return $this->post('/api/follow/'.rawurlencode($userId))['data'];
    }

    public function unfollow(string $userId): void
    {
        $this->delete('/api/follow/'.rawurlencode($userId));
    }

    /** `{data: [{user_id, followed_at}], meta}`, newest first. */
    public function followers(string $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/users/'.rawurlencode($userId).'/followers', ['page' => $page, 'per_page' => $perPage]);
    }

    /** Same shape as followers: `user_id` is the followed user. */
    public function following(string $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/users/'.rawurlencode($userId).'/following', ['page' => $page, 'per_page' => $perPage]);
    }

    /** `{followers, following, is_following}`; is_following is null for guests. */
    public function followStats(string $userId): array
    {
        return $this->get('/api/users/'.rawurlencode($userId).'/follow-stats')['data'];
    }
}
