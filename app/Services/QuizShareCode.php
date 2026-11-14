<?php

declare(strict_types=1);

namespace App\Services;

/** Generates and validates public quiz codes shared with students. */
final class QuizShareCode
{
    private const SHORT_PATTERN  = '/^[0-9]{9}$/D';

    // Generate a random nine-digit public quiz share code.
    public static function generate(): string
    {
        return str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
    }

    // Check the full public share-code format.
    public static function isValid(string $value): bool
    {
        return self::isShort($value);
    }

    // Detect a short share-code candidate.
    public static function isShort(string $value): bool
    {
        return preg_match(self::SHORT_PATTERN, $value) === 1;
    }
}
