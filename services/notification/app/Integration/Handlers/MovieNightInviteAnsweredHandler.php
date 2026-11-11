<?php

namespace App\Integration\Handlers;

use App\Services\NotificationService;
use MoviePlatform\Common\Integration\EventHandler;
use MoviePlatform\Common\MovieNights\ParticipantStatus;
use MoviePlatform\Common\Notifications\NotificationType;

class MovieNightInviteAnsweredHandler implements EventHandler
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(array $data, array $envelope): void
    {
        $answer = match (ParticipantStatus::from($data['status'])) {
            ParticipantStatus::Accepted => 'accepted',
            ParticipantStatus::Declined => 'declined',
        };

        $this->notifications->notify(
            $data['host_id'],
            NotificationType::MovieNightInviteAnswered,
            "{$data['user_name']} {$answer} your invitation",
            "See who's coming on the movie night page.",
            ['movie_night_id' => $data['movie_night_id']],
        );
    }
}
