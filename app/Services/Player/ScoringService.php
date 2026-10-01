<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

/** Equal-weight, all-or-nothing scoring mirrored by player-scoring.js. */
final class ScoringService
{
    public static function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/[\p{Z}\x{0009}-\x{000D}\x{0085}]+/u', ' ', $text) ?? ''), 'UTF-8');
    }

    /** @return array{answerCodes: list<string>, textAnswer: string} */
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

    /** @return array{result: string, isCorrect: bool} */
    public function grade(array $question, array $input): array
    {
        $answer = $this->answer($question, $input);
        if ($question['type'] === 'short_text') {
            $text = self::normalize($answer['textAnswer']);
            $correct = $text !== '' && in_array($text, array_map(self::normalize(...), $question['acceptedAnswers']), true);
            return ['result' => $text === '' ? 'unanswered' : ($correct ? 'correct' : 'wrong'), 'isCorrect' => $correct];
        }
        $codes = $answer['answerCodes'];
        if ($codes === []) return ['result' => 'unanswered', 'isCorrect' => false];
        $correct = array_values(array_unique(array_map('strval', $question['correctCodes'])));
        sort($correct, SORT_STRING);
        if ($correct === []) throw new PlayerException('invalid_quiz', 409);
        $isCorrect = $codes === $correct;
        return ['result' => $isCorrect ? 'correct' : 'wrong', 'isCorrect' => $isCorrect];
    }

    public static function percent(int $score, int $maximum): string
    {
        if ($maximum < 1) throw new PlayerException('invalid_quiz', 409);
        return number_format(round(($score * 100) / $maximum, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }

    public static function score(int $value): string
    {
        return number_format($value, 0, '.', '');
    }
}
