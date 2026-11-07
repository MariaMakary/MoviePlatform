<?php

namespace App\Enums;

enum SentimentLabel: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';
}
