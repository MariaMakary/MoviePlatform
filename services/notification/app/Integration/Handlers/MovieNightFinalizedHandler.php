<?php

namespace App\Integration\Handlers;

use App\Services\NotificationService;
use MoviePlatform\Common\Integration\EventHandler;
use MoviePlatform\Common\Notifications\NotificationType;

class MovieNightFinalizedHandler implements EventHandler
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(array $data, array $envelope): void
    {
        $participants = array_diff(array_unique($data['participant_ids']), [$data['host_id']]);

        foreach ($participants as $userId) {
            $this->notifications->notify(
                $userId,
                NotificationType::MovieNightFinalized,
                "Movie night: {$data['winner_movie_title']} won the vote",
                'See the time and place on the movie night page.',
                ['movie_night_id' => $data['movie_night_id']],
            );
        }
    }
}
