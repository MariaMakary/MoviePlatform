<?php

namespace App\Integration\Handlers;

use App\Services\NotificationService;
use MoviePlatform\Common\Integration\EventHandler;

class UserDeletedHandler implements EventHandler
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(array $data, array $envelope): void
    {
        $this->notifications->deleteAllFor($data['user_id']);
    }
}
