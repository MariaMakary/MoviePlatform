<?php

namespace App\Integration\Clients;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Hugging Face failed (timeout, rate limit, model loading…). In an event handler it makes the event retry (1124), then
 * go to the DLQ; in the API it answers 503.
 */
class SentimentUnavailable extends HttpException
{
    public function __construct(string $message = 'Hugging Face is unavailable.')
    {
        parent::__construct(503, $message);
    }
}
