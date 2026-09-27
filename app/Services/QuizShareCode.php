<?php

declare(strict_types=1);

namespace App\Services;

final class QuizShareCode
{
    private const SHORT_PATTERN  = '/^[0-9]{9}$/D';
    private const LEGACY_PATTERN = '/^[a-f0-9]{64}$/D';

    public static function generate(): string
    {
        return str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
    }

    public static function isValid(string $value): bool
    {
        return self::isShort($value)
            || preg_match(self::LEGACY_PATTERN, $value) === 1;
    }

    public static function isShort(string $value): bool
    {
        return preg_match(self::SHORT_PATTERN, $value) === 1;
    }
}
