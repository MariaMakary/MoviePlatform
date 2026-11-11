<?php

namespace App\Integration\Handlers;

use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use MoviePlatform\Common\Integration\EventHandler;
use MoviePlatform\Common\Notifications\NotificationType;

class MovieNightInviteSentHandler implements EventHandler
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(array $data, array $envelope): void
    {
        $when = Carbon::parse($data['scheduled_at'])
            ->setTimezone(config('notifications.display_timezone'))
            ->format('j M Y, H:i');

        $this->notifications->notify(
            $data['user_id'],
            NotificationType::MovieNightInvite,
            "{$data['host_name']} invited you to a movie night on {$when}",
            'Open the invitation to accept or decline.',
            ['movie_night_id' => $data['movie_night_id']],
        );
    }
}
