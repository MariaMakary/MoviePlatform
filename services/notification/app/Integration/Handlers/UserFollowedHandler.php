<?php

namespace App\Integration\Handlers;

use App\Services\NotificationService;
use MoviePlatform\Common\Integration\EventHandler;
use MoviePlatform\Common\Notifications\NotificationType;

class UserFollowedHandler implements EventHandler
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(array $data, array $envelope): void
    {
        $this->notifications->notify(
            $data['following_id'],
            NotificationType::NewFollower,
            "{$data['follower_name']} started following you",
            'See what they are watching on their profile.',
            ['follower_id' => $data['follower_id']],
        );
    }
}
