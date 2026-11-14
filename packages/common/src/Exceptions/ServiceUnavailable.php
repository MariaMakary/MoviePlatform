<?php

namespace MoviePlatform\Common\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceUnavailable extends HttpException
{
    public function __construct(public readonly string $service, ?\Throwable $previous = null)
    {
        parent::__construct(503, ucfirst($service).' is unavailable.', $previous);
    }
}
