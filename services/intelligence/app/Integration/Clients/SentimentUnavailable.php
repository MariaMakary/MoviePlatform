<?php

namespace App\Integration\Clients;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SentimentUnavailable extends HttpException
{
    public function __construct(string $message = 'Azure AI Language is unavailable.')
    {
        parent::__construct(503, $message);
    }
}
