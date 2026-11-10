<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ServiceUnavailable extends HttpException
{
    public function __construct(public readonly string $service, ?Throwable $previous = null)
    {
        parent::__construct(503, "{$service} service is unavailable.", $previous);
    }
}
