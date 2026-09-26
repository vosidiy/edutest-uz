<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class PlayerException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $fields = [],
    ) {
        parent::__construct(lang('Player.errors.' . $errorCode));
    }
}
