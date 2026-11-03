<?php

namespace App\Integration\Events;

use App\Models\Review;

/** Same fields as ReviewCreated, with the new body (docs/events.md). */
final class ReviewUpdated
{
    public const ROUTING_KEY = 'review.updated';

    public static function data(Review $review): array
    {
        return ReviewCreated::data($review);
    }
}
