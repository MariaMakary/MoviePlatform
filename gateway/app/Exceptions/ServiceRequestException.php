<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceRequestException extends HttpException
{
    public function __construct(public readonly string $service, int $status, string $message)
    {
        parent::__construct($status, $message);
    }
}
