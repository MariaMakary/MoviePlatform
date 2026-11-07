<?php

namespace App\Integration\Events;

use App\Models\SentimentResult;

final class ReviewAnalyzed
{
    public const ROUTING_KEY = 'review.analyzed';

    public static function data(SentimentResult $result): array
    {
        return [
            'review_id' => $result->review_id,
            'label' => $result->label->value,
            'score' => $result->score,
        ];
    }
}
