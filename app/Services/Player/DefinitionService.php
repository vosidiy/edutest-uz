<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

final class DefinitionService
{
    public function assertReady(array $document): void
    {
        $quiz = $document['quiz'] ?? null;
        $questions = $document['questions'] ?? null;
        $valid = is_array($quiz)
            && is_array($questions)
            && array_is_list($questions)
            && $questions !== []
            && trim((string) ($quiz['title'] ?? '')) !== ''
            && in_array($quiz['feedback'] ?? null, ['at_end', 'after_each'], true)
            && is_int($quiz['timeLimitSec'] ?? null)
            && $quiz['timeLimitSec'] >= 30
            && $quiz['timeLimitSec'] <= 7200
            && $quiz['timeLimitSec'] % 30 === 0
            && (! (bool) ($quiz['showExplain'] ?? false) || (bool) ($quiz['showAnswers'] ?? false));

        foreach (is_array($questions) ? $questions : [] as $question) {
            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            $codes = [];
            $valid = $valid
                && trim((string) ($question['content'] ?? '')) !== ''
                && in_array($question['optionsLayout'] ?? null, ['list', 'grid'], true)
                && count($options) >= 2
                && is_string($question['correctCode'] ?? null);

            foreach ($options as $option) {
                $code = $option['code'] ?? null;
                $valid = $valid
                    && is_string($code)
                    && $code !== ''
                    && ! isset($codes[$code])
                    && (trim((string) ($option['content'] ?? '')) !== '' || ($option['media'] ?? null) !== null);
                if (is_string($code)) {
                    $codes[$code] = true;
                }
            }

            $valid = $valid && isset($codes[(string) ($question['correctCode'] ?? '')]);
        }

        if (! $valid) {
            throw new PlayerException('invalid_quiz', 409);
        }
    }
}
