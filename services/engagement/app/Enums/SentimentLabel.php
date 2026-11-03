<?php

namespace App\Enums;

/** Set by Intelligence through review.analyzed (task 1186). */
enum SentimentLabel: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';
}
