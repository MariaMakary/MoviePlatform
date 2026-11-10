<?php

namespace App\Integration\Events;

use App\Models\MovieNight;

final class MovieNightInviteSent
{
    public const ROUTING_KEY = 'movie_night.invite_sent';

    public static function data(MovieNight $night, string $hostName, string $userId): array
    {
        return [
            'movie_night_id' => $night->id,
            'host_id' => $night->host_id,
            'host_name' => $hostName,
            'user_id' => $userId,
            'scheduled_at' => $night->scheduled_at->toIso8601ZuluString(),
        ];
    }
}
