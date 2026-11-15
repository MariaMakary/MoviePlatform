<?php

namespace App\Integration\Events;

use App\Models\MovieNight;

final class MovieNightFinalized
{
    public const ROUTING_KEY = 'movie_night.finalized';

    public static function data(MovieNight $night, array $participantIds, string $winnerTitle): array
    {
        return [
            'movie_night_id' => $night->id,
            'host_id' => $night->host_id,
            'participant_ids' => $participantIds,
            'winner_movie_id' => $night->winner_movie_id,
            'winner_movie_title' => $winnerTitle,
            'scheduled_at' => $night->scheduled_at->toIso8601ZuluString(),
            'location' => $night->location,
            'meeting_link' => $night->meeting_link,
        ];
    }
}
