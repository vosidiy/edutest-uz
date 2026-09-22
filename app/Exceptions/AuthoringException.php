<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class AuthoringException extends RuntimeException
{
    /** @param array<string, string> $fields */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
