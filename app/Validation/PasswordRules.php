<?php

declare(strict_types=1);

namespace App\Validation;

final class PasswordRules
{
    public function max_byte(mixed $value, string $maximum): bool
    {
        if (! is_string($value) || ! ctype_digit($maximum)) {
            return false;
        }

        return strlen($value) <= (int) $maximum;
    }
}
