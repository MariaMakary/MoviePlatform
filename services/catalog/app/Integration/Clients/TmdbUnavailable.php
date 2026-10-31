<?php

namespace App\Integration\Clients;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TmdbUnavailable extends HttpException
{
    public function __construct()
    {
        parent::__construct(503, 'TMDB is unavailable.');
    }
}
