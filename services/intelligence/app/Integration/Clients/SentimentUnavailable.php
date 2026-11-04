<?php

namespace App\Integration\Clients;

use RuntimeException;

/** Hugging Face failed (timeout, rate limit, model loading…): thrown so the event is retried (1124), then dead-lettered. */
class SentimentUnavailable extends RuntimeException
{
}
