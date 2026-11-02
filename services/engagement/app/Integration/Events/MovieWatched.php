<?php

namespace App\Integration\Events;

use App\Models\WatchHistoryEntry;

final class MovieWatched
{
    public const ROUTING_KEY = 'movie.watched';

    public static function data(WatchHistoryEntry $entry): array
    {
        return [
            'user_id' => $entry->user_id,
            'movie_id' => $entry->movie_id,
            'watched_at' => $entry->watched_at->toIso8601ZuluString(),
        ];
    }
}
