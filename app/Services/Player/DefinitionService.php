<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;
use App\Services\MediaService;

final class DefinitionService
{
    public function __construct(private readonly PlayerStore $store, private readonly MediaService $media) {}

    public function settings(array $quiz): array
    {
        $settings = ['mode' => $quiz['mode'], 'feedback' => $quiz['feedback'], 'emailMode' => $quiz['email_mode'], 'phoneMode' => $quiz['phone_mode'],
            'timeLimitSec' => $quiz['time_limit_sec'] === null ? null : (int) $quiz['time_limit_sec'],
            'closesAt' => PlayerStore::iso($quiz['closes_at']), 'timingPolicy' => 'client_enforced_offline_allowed'];
        foreach (['showScore' => 'show_score', 'showAnswers' => 'show_answers', 'showExplain' => 'show_explain',
            'shuffleQuestions' => 'shuffle_questions', 'shuffleOptions' => 'shuffle_options', 'cheatCheck' => 'cheat_check'] as $public => $column) {
            $settings[$public] = (bool) $quiz[$column];
        }
        return $settings;
    }

    public function document(array $quiz, array $settings, string $seed): array
    {
        $questions = [];
        $rows = $this->store->db->table('questions')->where('quiz_id', $quiz['id'])->orderBy('pos')->get()->getResultArray();
        foreach ($rows as $row) {
            $options = [];
            $correct = [];
            foreach ($this->store->db->table('question_options')->where('question_id', $row['id'])->orderBy('pos')->get()->getResultArray() as $option) {
                if ($option['is_correct']) $correct[] = (string) $option['code'];
                $options[] = ['id' => (string) $option['id'], 'code' => (string) $option['code'], 'content' => (string) $option['content'],
                    'media' => $this->media->descriptor($option['media_type'], $option['media_src'], 'option', (int) $option['id'])];
            }
            if ($settings['shuffleOptions']) $this->shuffle($options, $seed . ':' . $row['id']);
            $questions[] = ['id' => (string) $row['id'], 'type' => $row['type'], 'content' => $row['content'],
                'points' => ScoringService::decimal(ScoringService::cents((string) $row['points'])),
                'timeLimitSec' => $row['time_limit_sec'] === null ? null : (int) $row['time_limit_sec'],
                'media' => $this->media->descriptor($row['media_type'], $row['media_src'], 'question', (int) $row['id']),
                'explanation' => $settings['showExplain'] && $settings['showAnswers'] ? ($row['explanation'] ?? '') : '',
                'correctCodes' => $correct,
                'acceptedAnswers' => $row['type'] === 'short_text' ? (json_decode($row['text_answers'] ?? '[]', true) ?: []) : [],
                'options' => $options];
        }
        if ($settings['shuffleQuestions']) $this->shuffle($questions, $seed);
        return ['title' => $quiz['title'], 'description' => $quiz['description'], 'instructions' => $quiz['instructions'],
            'shareToken' => $quiz['share_token'], 'settings' => $settings,
            'cover' => $this->media->descriptor(($quiz['cover_src'] ?? null) === null ? null : 'image', $quiz['cover_src'] ?? null, 'cover', (int) $quiz['id']),
            'questions' => $questions];
    }

    public function assertReady(array $document): void
    {
        $settings = $document['settings'];
        $valid = trim($document['title']) !== '' && $document['questions'] !== []
            && (! $settings['showExplain'] || $settings['showAnswers'])
            && ($settings['mode'] !== 'practice' || (! $settings['cheatCheck'] && $settings['emailMode'] === 'hidden' && $settings['phoneMode'] === 'hidden'));
        foreach ($document['questions'] as $question) {
            $points = ScoringService::cents($question['points']);
            $valid = $valid && trim($question['content']) !== '' && $points > 0 && $points <= 1000000
                && ($question['timeLimitSec'] === null || ($question['timeLimitSec'] >= 1 && $question['timeLimitSec'] <= 86400));
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

    private function shuffle(array &$items, string $seed): void
    {
        usort($items, static fn (array $a, array $b): int => strcmp(hash_hmac('sha256', $a['id'], $seed), hash_hmac('sha256', $b['id'], $seed)));
    }
}
