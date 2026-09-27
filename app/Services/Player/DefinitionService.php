<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

final class DefinitionService
{
    /** @return array<string, mixed> */
    public function settings(array $quiz): array
    {
        $settings = [
            'mode' => $quiz['mode'], 'feedback' => $quiz['feedback'],
            'emailMode' => $quiz['email_mode'], 'phoneMode' => $quiz['phone_mode'],
            'timeLimitSec' => $quiz['time_limit_sec'] === null ? null : (int) $quiz['time_limit_sec'],
            'closesAt' => PlayerStore::iso($quiz['closes_at']),
            'timingPolicy' => 'client_enforced_offline_allowed',
        ];
        foreach (['showScore' => 'show_score', 'showAnswers' => 'show_answers', 'showExplain' => 'show_explain',
            'shuffleQuestions' => 'shuffle_questions', 'shuffleOptions' => 'shuffle_options', 'cheatCheck' => 'cheat_check'] as $public => $column) {
            $settings[$public] = (bool) $quiz[$column];
        }
        return $settings;
    }

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
