<?php

namespace App\Integration\Clients;

use Symfony\Component\HttpKernel\Exception\HttpException;

class WeatherUnavailable extends HttpException
{
    public function __construct(string $message = 'Weather is unavailable.')
    {
        parent::__construct(503, $message);
    }
}
