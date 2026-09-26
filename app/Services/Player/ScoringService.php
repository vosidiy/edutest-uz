<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

/** Integer hundredths only; mirrored by player-scoring.js and shared fixtures. */
final class ScoringService
{
    public static function cents(string $decimal): int
    {
        if (! preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D', $decimal, $parts)) {
            throw new PlayerException('invalid_quiz', 409);
        }
        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function roundedRatio(int $numerator, int $denominator): int
    {
        return intdiv($numerator, $denominator) + (($numerator % $denominator) * 2 >= $denominator ? 1 : 0);
    }

    public static function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/[\p{Z}\x{0009}-\x{000D}\x{0085}]+/u', ' ', $text) ?? ''), 'UTF-8');
    }

    public function answer(array $question, array $input): array
    {
        $codes = $input['answerCodes'] ?? [];
        $text = $input['textAnswer'] ?? '';
        if (! is_array($codes) || ! array_is_list($codes) || ! is_string($text)
            || ! mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > 500) {
            throw new PlayerException('invalid_answer');
        }
        if ($question['type'] === 'short_text') {
            if ($codes !== []) throw new PlayerException('invalid_answer');
            return ['answerCodes' => [], 'textAnswer' => $text];
        }
        $allowed = array_column($question['options'], 'code');
        if ($text !== '' || count($codes) > count($allowed)
            || ($question['type'] === 'single_choice' && count($codes) > 1)) {
            throw new PlayerException('invalid_answer');
        }
        $seen = [];
        foreach ($codes as $code) {
            if (! is_string($code) || ! in_array($code, $allowed, true) || isset($seen[$code])) {
                throw new PlayerException('invalid_answer');
            }
            $seen[$code] = true;
        }
        sort($codes, SORT_STRING);
        return ['answerCodes' => $codes, 'textAnswer' => ''];
    }

    public function grade(array $question, array $input): array
    {
        $answer = $this->answer($question, $input);
        $maximum = self::cents($question['points']);
        if ($question['type'] === 'short_text') {
            $text = self::normalize($answer['textAnswer']);
            $correct = $text !== '' && in_array($text, array_map(self::normalize(...), $question['acceptedAnswers']), true);
            return ['result' => $text === '' ? 'unanswered' : ($correct ? 'correct' : 'wrong'), 'points' => self::decimal($correct ? $maximum : 0)];
        }
        $codes = $answer['answerCodes'];
        if ($codes === []) return ['result' => 'unanswered', 'points' => '0.00'];
        $right = count(array_intersect($codes, $question['correctCodes']));
        $totalRight = count($question['correctCodes']);
        if ($totalRight < 1) throw new PlayerException('invalid_quiz', 409);
        if ($question['type'] === 'single_choice') {
            return ['result' => $right === 1 ? 'correct' : 'wrong', 'points' => self::decimal($right === 1 ? $maximum : 0)];
        }
        $wrong = count($codes) - $right;
        $totalWrong = count($question['options']) - $totalRight;
        $denominator = $totalRight * max(1, $totalWrong);
        $numerator = max(0, $right * max(1, $totalWrong) - $wrong * $totalRight);
        $award = self::roundedRatio($maximum * $numerator, $denominator);
        return ['result' => $right === $totalRight && $wrong === 0 ? 'correct' : ($award > 0 ? 'partial' : 'wrong'), 'points' => self::decimal($award)];
    }
}
