<?php

namespace App\Integration\Events;

use App\Models\Review;

final class ReviewCreated
{
    public const ROUTING_KEY = 'review.created';

    public static function data(Review $review): array
    {
        return [
            'review_id' => $review->id,
            'user_id' => $review->user_id,
            'movie_id' => $review->movie_id,
            'body' => $review->body,
        ];
    }
}
