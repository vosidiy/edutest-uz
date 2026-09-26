<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

final class AssessmentService
{
    public function __construct(private readonly PlayerStore $store, private readonly DefinitionService $definitions,
        private readonly ScoringService $scoring) {}

    public function load(string $publicId, string $token): array
    {
        $attempt = $this->authorize($publicId, $token);
        $quiz = $this->store->db->table('quizzes')->where('id', $attempt['quiz_id'])->get()->getRowArray();
        if ($quiz === null) throw new PlayerException('not_found', 404);
        $settings = json_decode($attempt['settings'], true, 512, JSON_THROW_ON_ERROR);
        $document = $this->definitions->document($quiz, $settings, $settings['seed']);
        unset($document['settings']['seed']);
        $items = $this->items($attempt['id']);
        // Persisted orders, not current teacher shuffle settings, are authoritative.
        $questions = array_column($document['questions'], null, 'id');
        $document['questions'] = [];
        foreach ($items as $item) {
            $question = $questions[$item['question_id']];
            $options = array_column($question['options'], null, 'code');
            $question['options'] = array_map(static fn (string $code): array => $options[$code], json_decode($item['choice_order'], true, 512, JSON_THROW_ON_ERROR));
            $document['questions'][] = $question;
        }
        return ['mode' => 'assessment', 'attemptId' => $publicId, 'version' => (int) $attempt['version'],
            'status' => $attempt['status'], 'phase' => $attempt['phase'], 'currentPosition' => $attempt['current_pos'] === null ? null : (int) $attempt['current_pos'],
            'startedAt' => PlayerStore::iso($attempt['started_at']), 'totalDueAt' => PlayerStore::iso($attempt['total_due_at']),
            'closeAt' => PlayerStore::iso($attempt['close_at']), 'lateSync' => (bool) $attempt['late_sync'],
            'finishReason' => $attempt['finish_reason'], 'quiz' => $document,
            'items' => array_map($this->serializeItem(...), $items),
            'result' => $attempt['status'] === 'in_progress' ? null : $this->result($attempt, $settings, $items)];
    }

    public function sync(string $publicId, string $token, array $payload): array
    {
        $authorized = $this->authorize($publicId, $token);
        if (! is_int($payload['version'] ?? null) || $payload['version'] < 1 || $payload['version'] > 4294967295 || ! is_array($payload['items'] ?? null)
            || ! array_is_list($payload['items']) || count($payload['items']) > 100) throw new PlayerException('invalid_progress');
        $finish = $payload['finishReason'] ?? null;
        if ($finish !== null && ! in_array($finish, ['completed', 'total_timeout', 'scheduled_close'], true)) throw new PlayerException('invalid_progress');
        $this->store->transaction(function () use ($authorized, $payload, $finish): void {
            $quiz = $this->store->lock('quizzes', (string) $authorized['quiz_id']);
            $attempt = $this->store->lock('attempts', (string) $authorized['id']);
            $settings = json_decode($attempt['settings'], true, 512, JSON_THROW_ON_ERROR);
            $questions = array_column($this->definitions->document($quiz, $settings, $settings['seed'])['questions'], null, 'id');
            $items = array_column($this->items($attempt['id']), null, 'question_id');
            $now = PlayerStore::now();
            $changed = false;
            $late = (bool) $attempt['late_sync'];
            $seen = [];
            $previousPos = 0;
            foreach ($payload['items'] as $input) {
                if (! is_array($input) || ! is_string($input['questionId'] ?? null) || ! isset($items[$input['questionId']])
                    || isset($seen[$input['questionId']]) || ! is_int($input['saveVer'] ?? null) || $input['saveVer'] < 1 || $input['saveVer'] > 4294967295) throw new PlayerException('invalid_progress');
                $id = $input['questionId'];
                $seen[$id] = true;
                $item = $items[$id];
                if ((int) $item['pos'] <= $previousPos) throw new PlayerException('invalid_progress');
                $previousPos = (int) $item['pos'];
                $question = $questions[$id];
                $answer = $this->scoring->answer($question, $input);
                $submitKey = $input['submitKey'] ?? null;
                $reason = $input['reason'] ?? 'answered';
                if ($submitKey !== null && (! is_string($submitKey) || ! preg_match('/^[a-f0-9]{32}$/D', $submitKey)
                    || ! in_array($reason, ['answered', 'skipped', 'question_timeout', 'attempt_timeout'], true))) throw new PlayerException('invalid_progress');
                if ($reason === 'skipped') $answer = ['answerCodes' => [], 'textAnswer' => ''];
                $hash = hash('sha256', json_encode([$id, $answer, $reason], JSON_THROW_ON_ERROR), true);
                if ($item['status'] === 'locked') {
                    if ($submitKey !== $item['submit_key'] || $item['submit_hash'] === null || ! hash_equals($item['submit_hash'], $hash)) throw new PlayerException('answer_locked', 409);
                    continue;
                }
                if ($attempt['status'] !== 'in_progress') throw new PlayerException('attempt_finalized', 409);
                // Submitted questions form a prefix; a request cannot jump past an unanswered question.
                foreach ($items as $earlier) {
                    if ((int) $earlier['pos'] < (int) $item['pos'] && $earlier['status'] !== 'locked') throw new PlayerException('invalid_progress');
                }
                if (! is_string($input['startedAt'] ?? null)) throw new PlayerException('invalid_progress');
                $started = PlayerStore::date($input['startedAt']);
                if ($started < $attempt['started_at'] || $started > PlayerStore::due($now, 5)) throw new PlayerException('invalid_progress');
                if ($item['started_at'] !== null && $item['started_at'] !== $started) throw new PlayerException('progress_conflict', 409);
                foreach ($items as $earlier) {
                    if ((int) $earlier['pos'] < (int) $item['pos'] && $earlier['started_at'] !== null && $earlier['started_at'] > $started) throw new PlayerException('invalid_progress');
                }
                $savedAnswer = ['answerCodes' => json_decode($item['answer_codes'] ?? '[]', true), 'textAnswer' => $item['text_answer'] ?? ''];
                if ($input['saveVer'] < (int) $item['save_ver']) continue;
                if ($input['saveVer'] === (int) $item['save_ver']) {
                    if ($answer !== $savedAnswer || $submitKey !== null) throw new PlayerException('progress_conflict', 409);
                    continue;
                }
                $due = PlayerStore::due($started, $question['timeLimitSec']);
                $update = ['answer_codes' => json_encode($answer['answerCodes'], JSON_THROW_ON_ERROR),
                    'text_answer' => $question['type'] === 'short_text' ? $answer['textAnswer'] : null,
                    'status' => $submitKey === null ? 'active' : 'locked', 'started_at' => $started, 'due_at' => $due,
                    'save_ver' => $input['saveVer'], 'saved_at' => $now];
                if ($submitKey !== null) {
                    foreach ($items as $other) if ($other['submit_key'] === $submitKey) throw new PlayerException('invalid_progress');
                    $update += $this->scoring->grade($question, $answer) + ['locked_at' => $now, 'lock_reason' => $reason, 'submit_key' => $submitKey, 'submit_hash' => PlayerStore::binary($hash)];
                }
                $this->store->db->table('attempt_items')->where('id', $item['id'])->update($update);
                if ($submitKey !== null) $update['submit_hash'] = $hash;
                $items[$id] = array_replace($item, $update);
                $changed = true;
                $late = $late || $this->past($now, [$due, $attempt['total_due_at'], $attempt['close_at']]);
            }
            if ($finish !== null && $attempt['status'] === 'in_progress') {
                if (($finish === 'total_timeout' && $attempt['total_due_at'] === null) || ($finish === 'scheduled_close' && $attempt['close_at'] === null)) throw new PlayerException('invalid_progress');
                foreach ($items as $id => $item) {
                    if ($item['status'] === 'locked') continue;
                    if ($finish === 'completed') throw new PlayerException('invalid_progress');
                    $answer = ['answerCodes' => json_decode($item['answer_codes'] ?? '[]', true), 'textAnswer' => $item['text_answer'] ?? ''];
                    $update = $this->scoring->grade($questions[$id], $answer) + ['status' => 'locked', 'locked_at' => $now, 'lock_reason' => 'attempt_timeout'];
                    $this->store->db->table('attempt_items')->where('id', $item['id'])->update($update);
                    $items[$id] = array_replace($item, $update);
                }
                $changed = true;
                $late = $late || $this->past($now, [$attempt['total_due_at'], $attempt['close_at']]);
            }
            if (! $changed) return; // Lost acknowledgement replay; no version increment.
            if ((int) $attempt['version'] !== $payload['version']) throw new PlayerException('progress_conflict', 409);
            $update = ['version' => (int) $attempt['version'] + 1, 'updated_at' => $now, 'late_sync' => $late ? 1 : 0];
            $active = array_values(array_filter($items, static fn (array $item): bool => $item['status'] === 'active'));
            $locked = array_values(array_filter($items, static fn (array $item): bool => $item['status'] === 'locked'));
            $update += ['phase' => $active === [] ? 'feedback' : 'answering', 'current_pos' => $active[0]['pos'] ?? max(1, count($locked)),
                'due_at' => $this->earliest([$active[0]['due_at'] ?? null, $attempt['total_due_at'], $attempt['close_at']])];
            if ($finish !== null) {
                $score = array_sum(array_map(static fn (array $item): int => ScoringService::cents((string) ($item['points'] ?? '0.00')), $items));
                $update = array_replace($update, ['status' => $finish === 'completed' ? 'submitted' : 'expired', 'phase' => 'complete', 'current_pos' => null,
                    'due_at' => null, 'submitted_at' => $now, 'finish_reason' => $finish, 'score' => ScoringService::decimal($score),
                    'percent' => ScoringService::decimal(ScoringService::roundedRatio($score * 10000, ScoringService::cents((string) $attempt['max_score'])))]);
            }
            $this->store->db->table('attempts')->where('id', $attempt['id'])->update($update);
        });
        return $this->load($publicId, $token);
    }

    public function results(string $publicId, string $token): array
    {
        $data = $this->load($publicId, $token);
        if ($data['result'] === null) throw new PlayerException('not_finished', 409);
        return $data;
    }

    public function events(string $publicId, string $token, array $input): array
    {
        $attempt = $this->authorize($publicId, $token);
        $settings = json_decode($attempt['settings'], true, 512, JSON_THROW_ON_ERROR);
        if (! $settings['cheatCheck']) throw new PlayerException('monitoring_disabled', 403);
        $events = $input['events'] ?? null;
        if (! is_array($events) || ! array_is_list($events) || count($events) > 100) throw new PlayerException('invalid_progress');
        $this->store->transaction(function () use ($attempt, $events): void {
            $this->store->lock('quizzes', (string) $attempt['quiz_id']);
            $this->store->lock('attempts', (string) $attempt['id']);
            foreach ($events as $event) {
                if (! is_array($event) || ! is_string($event['key'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $event['key'])
                    || ! in_array($event['type'] ?? null, ['tab_hidden', 'tab_visible', 'window_blur', 'window_focus', 'fullscreen_exit', 'inactivity_start', 'inactivity_end'], true)
                    || ! is_string($event['happenedAt'] ?? null)) throw new PlayerException('invalid_progress');
                $date = PlayerStore::date($event['happenedAt']);
                $duration = $event['durationMs'] ?? null;
                if ($duration !== null && (! is_int($duration) || $duration < 0 || $duration > 4294967295)) throw new PlayerException('invalid_progress');
                if ($this->store->db->table('cheat_events')->where('attempt_id', $attempt['id'])->where('event_key', $event['key'])->countAllResults() > 0) continue;
                $this->store->db->table('cheat_events')->insert(['attempt_id' => $attempt['id'], 'event_key' => $event['key'], 'type' => $event['type'],
                    'happened_at' => $date, 'received_at' => PlayerStore::now(), 'duration_ms' => $duration, 'data' => '{}']);
            }
        });
        return ['accepted' => true];
    }

    private function authorize(string $publicId, string $token): array
    {
        if (! preg_match('/^[a-f0-9]{32}$/D', $publicId) || ! preg_match('/^[a-f0-9]{64}$/D', $token)) throw new PlayerException('invalid_credential', 401);
        $attempt = $this->store->db->table('attempts')->where('public_id', $publicId)->get()->getRowArray();
        if ($attempt === null || ! hash_equals($attempt['token_hash'], hash('sha256', $token, true))) throw new PlayerException('invalid_credential', 401);
        return $attempt;
    }

    private function items(string|int $attemptId): array
    {
        return $this->store->db->table('attempt_items')->where('attempt_id', $attemptId)->orderBy('pos')->get()->getResultArray();
    }

    private function serializeItem(array $item): array
    {
        return ['questionId' => (string) $item['question_id'], 'status' => $item['status'], 'saveVer' => (int) $item['save_ver'],
            'startedAt' => PlayerStore::iso($item['started_at']), 'dueAt' => PlayerStore::iso($item['due_at']),
            'answerCodes' => json_decode($item['answer_codes'] ?? '[]', true), 'textAnswer' => $item['text_answer'] ?? '',
            'submitKey' => $item['submit_key'], 'reason' => $item['lock_reason']];
    }

    private function result(array $attempt, array $settings, array $items): array
    {
        $result = ['confirmed' => true, 'lateSync' => (bool) $attempt['late_sync'], 'timingPolicy' => $settings['timingPolicy'],
            'submittedAt' => PlayerStore::iso($attempt['submitted_at']), 'items' => []];
        if ($settings['showScore']) {
            foreach (['score', 'max_score' => 'maxScore', 'percent'] as $key => $value) {
                $column = is_int($key) ? $value : $key;
                $result[$value] = ScoringService::decimal(ScoringService::cents((string) $attempt[$column]));
            }
        }
        foreach ($items as $item) {
            $row = ['questionId' => (string) $item['question_id'], 'result' => $item['result']];
            if ($settings['showScore']) $row['points'] = ScoringService::decimal(ScoringService::cents((string) $item['points']));
            $result['items'][] = $row;
        }
        return $result;
    }

    private function earliest(array $values): ?string
    {
        $values = array_values(array_filter($values, static fn ($value): bool => $value !== null));
        return $values === [] ? null : min($values);
    }

    private function past(string $now, array $deadlines): bool
    {
        $due = $this->earliest($deadlines);
        return $due !== null && $now > $due;
    }
}
