<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

/** Equal-weight, server-authoritative single-choice scoring. */
final class ScoringService
{
    public function validateCode(array $question, mixed $code, bool $nullable = true): ?string
    {
        if ($code === null && $nullable) {
            return null;
        }
        if (! is_string($code) || $code === '') {
            throw new PlayerException('invalid_answer');
        }

        foreach ($question['options'] as $option) {
            if (hash_equals((string) $option['code'], $code)) {
                return $code;
            }
        }

        throw new PlayerException('invalid_answer');
    }

    public function isCorrect(array $question, ?string $code): bool
    {
        return $code !== null && hash_equals((string) $question['correctCode'], $code);
    }

    public static function percent(int $score, int $maximum): string
    {
        if ($maximum < 1) {
            throw new PlayerException('invalid_quiz', 409);
        }

        return number_format(round(($score * 100) / $maximum, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }
}
