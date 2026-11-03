<?php

namespace App\Integration\Events;

use App\Enums\ParticipantStatus;
use App\Models\MovieNight;

final class MovieNightInviteAnswered
{
    public const ROUTING_KEY = 'movie_night.invite_answered';

    public static function data(MovieNight $night, string $userId, string $userName, ParticipantStatus $status): array
    {
        return [
            'movie_night_id' => $night->id,
            'host_id' => $night->host_id,
            'user_id' => $userId,
            'user_name' => $userName,
            'status' => $status->value,
        ];
    }
}
