<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

final class DefinitionService
{
    public function assertReady(array $document): void
    {
        $settings = $document['settings'];
        $valid = trim($document['title']) !== '' && $document['questions'] !== []
            && (! $settings['showExplain'] || $settings['showAnswers'])
            && ($settings['mode'] !== 'practice' || (! $settings['cheatCheck'] && $settings['emailMode'] === 'hidden' && $settings['phoneMode'] === 'hidden'));
        foreach ($document['questions'] as $question) {
            $valid = $valid && trim($question['content']) !== '';
            if ($question['type'] === 'short_text') {
                $valid = $valid && count(array_filter($question['acceptedAnswers'], static fn ($answer): bool => is_string($answer) && ScoringService::normalize($answer) !== '')) > 0;
            } else {
                $valid = $valid && count($question['options']) >= 2 && count($question['correctCodes']) >= 1
                    && ($question['type'] === 'multi_select' || ($question['type'] === 'single_choice' && count($question['correctCodes']) === 1));
                foreach ($question['options'] as $option) $valid = $valid && (trim($option['content']) !== '' || $option['media'] !== null);
            }
        }
        if (! $valid) throw new PlayerException('invalid_quiz', 409);
    }
}
