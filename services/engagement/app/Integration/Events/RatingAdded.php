<?php

namespace App\Integration\Events;

use App\Models\Rating;

final class RatingAdded
{
    public const ROUTING_KEY = 'rating.added';

    public static function data(Rating $rating): array
    {
        return [
            'rating_id' => $rating->id,
            'user_id' => $rating->user_id,
            'movie_id' => $rating->movie_id,
            'score' => $rating->score,
        ];
    }
}
