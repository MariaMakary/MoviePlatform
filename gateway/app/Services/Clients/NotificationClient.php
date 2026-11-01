<?php

namespace App\Services\Clients;

class NotificationClient extends BaseClient
{
    protected string $service = 'notification';

    public function notifications(int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/notifications', ['page' => $page, 'per_page' => $perPage]);
    }

    public function unreadCount(): int
    {
        return $this->get('/api/notifications/unread-count')['data']['count'];
    }

    public function markRead(string $id): array
    {
        return $this->patch('/api/notifications/'.rawurlencode($id).'/read')['data'];
    }

    public function markAllRead(): void
    {
        $this->patch('/api/notifications/read-all');
    }
}
