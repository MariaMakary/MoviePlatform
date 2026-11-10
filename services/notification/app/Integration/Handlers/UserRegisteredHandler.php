<?php

namespace App\Integration\Handlers;

use MoviePlatform\Common\Notifications\NotificationType;
use App\Services\NotificationService;
use MoviePlatform\Common\Integration\EventHandler;

class UserRegisteredHandler implements EventHandler
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(array $data, array $envelope): void
    {
        $this->notifications->notify(
            $data['user_id'],
            NotificationType::Welcome,
            'Welcome to KAIA!',
            "Hi {$data['name']}, rate movies, build your watchlist and plan movie nights with friends.",
        );
    }
}
