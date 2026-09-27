<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ReportingException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 404,
    ) {
        parent::__construct($message);
    }
}
