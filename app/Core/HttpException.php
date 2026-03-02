<?php

declare(strict_types=1);

namespace Blogme\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(private readonly int $statusCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : (string) $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
