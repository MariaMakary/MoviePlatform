<?php

namespace MoviePlatform\Common\Exceptions;

use RuntimeException;

class InternalRequestFailed extends RuntimeException
{
    public function __construct(public readonly string $service, public readonly int $status, string $message)
    {
        parent::__construct(ucfirst($service)." refused the request ({$status}): {$message}");
    }
}
