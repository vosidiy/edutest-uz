<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;
use App\Services\QuizPaperService;

final class AssessmentService
{
    public function __construct(
        private readonly PlayerStore $store,
        private readonly QuizPaperService $papers,
        private readonly ScoringService $scoring,
    ) {}

    public function load(string $publicId, string $token): array
    {
        $this->mutate($this->authorize($publicId, $token), fn (array $attempt): array => $attempt);
        return $this->serialize($this->authorize($publicId, $token));
    }

    public function answer(string $publicId, string $questionId, string $token, array $input): array
    {
        return $this->mutate($this->authorize($publicId, $token), function (array $attempt) use ($questionId, $input): array {
            if (! ctype_digit($questionId) || ! $this->hasExactKeys($input, ['status', 'answerCodes', 'textAnswer', 'clientAnsweredAt', 'clientActivityAt'])
                || ! in_array($input['status'], ['answered', 'skipped'], true)) throw new PlayerException('invalid_answer');
            $paper = $this->papers->findForAttempt($attempt);
            $questions = $this->questions($paper);
            if (! isset($questions[$questionId])) throw new PlayerException('invalid_answer');
            $row = $this->store->db->table('attempt_answers')->where('attempt_id', $attempt['id'])->where('question_id', $questionId)->get()->getRowArray();
            if ($row === null) throw new PlayerException('invalid_answer');
            $now = PlayerStore::now();
            $answered = $this->clientTime($input['clientAnsweredAt'], $attempt, $now);
            $activity = $this->clientTime($input['clientActivityAt'], $attempt, $now);
            if ($answered > $activity) throw new PlayerException('invalid_timing');
            $normalized = $this->normalizedConfirmation($questions[$questionId], $input);
            if ($row['status'] !== 'not_reached') {
                if (! $this->sameConfirmation($row, $normalized)
                    || ($row['client_answered_at'] !== null && $row['client_answered_at'] !== $answered)) throw new PlayerException('answer_locked', 409);
                return $this->ack($attempt) + ['questionId' => $questionId, 'answerStatus' => $row['status'], 'receivedAt' => PlayerStore::iso($row['answered_at'])];
            }
            if ($attempt['status'] === 'completed') throw new PlayerException('attempt_finalized', 409);
            $changes = $this->activityChanges($attempt, $this->papers->settings($paper), $activity, $now);
            if ($answered >= $changes['expires_at']) throw new PlayerException('answer_after_deadline', 422);
            $correct = $input['status'] === 'skipped' ? false : $this->scoring->grade($questions[$questionId], [
                'answerCodes' => $normalized['answerCodes'] ?? [], 'textAnswer' => $normalized['textAnswer'] ?? '',
            ])['isCorrect'];
            $this->store->db->table('attempt_answers')->where('id', $row['id'])->where('status', 'not_reached')->update([
                'status' => $input['status'],
                'selected_option_codes' => $normalized['answerCodes'] === null ? null : json_encode($normalized['answerCodes'], JSON_THROW_ON_ERROR),
                'text_answer' => $normalized['textAnswer'], 'is_correct' => $correct ? 1 : 0,
                'answered_at' => $now, 'client_answered_at' => $answered,
            ]);
            $attempt = $this->applyActivity($attempt, $changes, $now);
            return $this->ack($attempt) + ['questionId' => $questionId, 'answerStatus' => $input['status'], 'receivedAt' => PlayerStore::iso($now)];
        });
    }

    public function activity(string $publicId, string $token, array $input): array
    {
        return $this->mutate($this->authorize($publicId, $token), function (array $attempt) use ($input): array {
            if (! $this->hasExactKeys($input, ['clientActivityAt'])) throw new PlayerException('invalid_progress');
            $now = PlayerStore::now();
            $activity = $this->clientTime($input['clientActivityAt'], $attempt, $now);
            if ($attempt['status'] === 'completed') return $this->ack($attempt);
            $settings = $this->papers->settings($this->papers->findForAttempt($attempt));
            if ($settings['timeLimitSec'] !== null) throw new PlayerException('invalid_progress');
            if ($activity <= $attempt['client_activity_at']) return $this->ack($attempt);
            return $this->ack($this->applyActivity($attempt, $this->activityChanges($attempt, $settings, $activity, $now), $now));
        });
    }

    public function finish(string $publicId, string $token, array $input): array
    {
        return $this->mutate($this->authorize($publicId, $token), function (array $attempt) use ($input): array {
            if (! $this->hasExactKeys($input, ['finishReason', 'clientFinishedAt', 'clientActivityAt', 'confirmedCount'])
                || ! in_array($input['finishReason'], ['completed', 'quit', 'timer_expired', 'scheduled_close', 'stale_timeout'], true)
                || ! is_int($input['confirmedCount']) || $input['confirmedCount'] < 0 || $input['confirmedCount'] > (int) $attempt['max_score']) throw new PlayerException('invalid_progress');
            $now = PlayerStore::now();
            $finished = $this->clientTime($input['clientFinishedAt'], $attempt, $now);
            $activity = $this->clientTime($input['clientActivityAt'], $attempt, $now);
            $rows = $this->answerRows($attempt['id']);
            $confirmed = array_filter($rows, static fn (array $row): bool => $row['status'] !== 'not_reached');
            if (count($confirmed) < $input['confirmedCount']) throw new PlayerException('incomplete_sync', 409);
            if (count($confirmed) !== $input['confirmedCount']) throw new PlayerException('finish_conflict', 409);
            foreach ($rows as $index => $row) {
                if (($index < $input['confirmedCount']) !== ($row['status'] !== 'not_reached')) throw new PlayerException('incomplete_sync', 409);
                if ($row['client_answered_at'] !== null && $row['client_answered_at'] > $finished) throw new PlayerException('invalid_timing');
            }
            $paper = $this->papers->findForAttempt($attempt);
            $settings = $this->papers->settings($paper);
            if ($attempt['status'] === 'completed') {
                if ($attempt['ended_reason'] !== $input['finishReason'] || $attempt['finished_at'] !== $finished
                    || $activity !== $attempt['client_activity_at']) throw new PlayerException('finish_conflict', 409);
                return $this->ack($attempt) + ['result' => $this->result($attempt, $rows, $settings)];
            }
            if ($activity > $finished || $activity < $attempt['client_activity_at']) throw new PlayerException('invalid_timing');
            $changes = $this->activityChanges($attempt, $settings, $activity, $now);
            if ($input['finishReason'] === 'completed') {
                if ($input['confirmedCount'] !== (int) $attempt['max_score']) throw new PlayerException('incomplete_sync', 409);
                if ($finished >= $changes['expires_at']) throw new PlayerException('invalid_timing');
            } elseif ($input['finishReason'] === 'quit') {
                if ($finished >= $changes['expires_at']) throw new PlayerException('invalid_timing');
            } elseif ($input['finishReason'] !== $changes['deadline_reason'] || $finished !== $changes['expires_at']) {
                throw new PlayerException('invalid_timing');
            }
            $questions = $this->questions($paper);
            $score = 0;
            foreach ($rows as &$row) {
                if ($row['status'] !== 'answered') continue;
                $correct = $this->scoring->grade($questions[(string) $row['question_id']], [
                    'answerCodes' => $this->decodeCodes($row['selected_option_codes']), 'textAnswer' => (string) ($row['text_answer'] ?? ''),
                ])['isCorrect'];
                if ($correct) $score++;
                $row['is_correct'] = $correct ? 1 : 0;
            }
            unset($row);
            // All validation and grading precede writes.
            foreach ($rows as $row) if ($row['status'] === 'answered') {
                $this->store->db->table('attempt_answers')->where('id', $row['id'])->update(['is_correct' => $row['is_correct']]);
            }
            $changes += ['status' => 'completed', 'finished_at' => $finished, 'ended_reason' => $input['finishReason'],
                'score' => $score, 'percent' => ScoringService::percent($score, (int) $attempt['max_score'])];
            $this->store->db->table('attempts')->where('id', $attempt['id'])->update($changes);
            $attempt = array_replace($attempt, $changes);
            return $this->ack($attempt) + ['result' => $this->result($attempt, $rows, $settings)];
        });
    }

    public function results(string $publicId, string $token): array
    {
        $data = $this->load($publicId, $token);
        if ($data['result'] === null) throw new PlayerException('not_finished', 409);
        return $data;
    }

    public function events(string $publicId, string $token, array $input): array
    {
        return $this->mutate($this->authorize($publicId, $token), function (array $attempt) use ($input): array {
            $events = $input['events'] ?? null;
            if (! is_array($events) || ! array_is_list($events) || count($events) > 100) throw new PlayerException('invalid_progress');
            if (! $this->papers->settings($this->papers->findForAttempt($attempt))['cheatCheck']) throw new PlayerException('monitoring_disabled', 403);
            $now = PlayerStore::now();
            $validated = [];
            foreach ($events as $event) {
                if (! is_array($event) || ! $this->hasExactKeys($event, ['key', 'type', 'happenedAt', 'durationMs'])
                    || ! is_string($event['key']) || preg_match('/^[a-f0-9]{32}$/D', $event['key']) !== 1
                    || ! in_array($event['type'], ['tab_hidden', 'fullscreen_exit'], true)
                    || ($event['durationMs'] !== null && (! is_int($event['durationMs']) || $event['durationMs'] < 0 || $event['durationMs'] > 4294967295))) throw new PlayerException('invalid_progress');
                $at = $this->clientTime($event['happenedAt'], $attempt, $now);
                if ($at > ($attempt['status'] === 'completed' ? $attempt['finished_at'] : $attempt['expires_at'])) throw new PlayerException('invalid_timing');
                $validated[] = ['attempt_id' => $attempt['id'], 'event_key' => $event['key'], 'type' => $event['type'],
                    'happened_at' => $at, 'received_at' => $now, 'duration_ms' => $event['durationMs'], 'data' => '{}'];
            }
            foreach ($validated as $event) {
                if ($this->store->db->table('cheat_events')->where('attempt_id', $attempt['id'])->where('event_key', $event['event_key'])->countAllResults() === 0) {
                    $this->store->db->table('cheat_events')->insert($event);
                }
            }
            return ['accepted' => true];
        });
    }

    public function media(string $publicId, string $token): array { return $this->load($publicId, $token); }

    public function resolveQuizOverdue(int $quizId, int $limit = 250): void
    {
        $ids = array_column($this->store->db->table('attempts')->select('id')->where('quiz_id', $quizId)
            ->where('status', 'in_progress')->where('expires_at <=', PlayerStore::now())->orderBy('expires_at')
            ->limit(max(1, min(1000, $limit)))->get()->getResultArray(), 'id');
        foreach ($ids as $id) $this->mutate(['id' => $id], fn (array $attempt): array => $attempt);
    }

    /** Commit lazy expiry even when subsequent action validation fails. Callbacks validate before writing. */
    private function mutate(array $authorized, callable $operation): array
    {
        $result = $this->store->transaction(function () use ($authorized, $operation): array|PlayerException {
            $attempt = $this->resolveExpiryLocked($this->store->lock('attempts', (string) $authorized['id']));
            try { return $operation($attempt); }
            catch (PlayerException $error) { return $error; }
        });
        if ($result instanceof PlayerException) throw $result;
        return $result;
    }

    private function resolveExpiryLocked(array $attempt): array
    {
        return $attempt['status'] === 'in_progress' && PlayerStore::now() >= $attempt['expires_at'] ? $this->abandon($attempt) : $attempt;
    }

    private function abandon(array $attempt): array
    {
        $score = (int) ($this->store->db->table('attempt_answers')->selectSum('is_correct', 'score')->where('attempt_id', $attempt['id'])->get()->getRowArray()['score'] ?? 0);
        $changes = ['status' => 'abandoned', 'finished_at' => $attempt['expires_at'], 'ended_reason' => $attempt['deadline_reason'],
            'score' => $score, 'percent' => ScoringService::percent($score, (int) $attempt['max_score'])];
        $this->store->db->table('attempts')->where('id', $attempt['id'])->update($changes);
        return array_replace($attempt, $changes);
    }

    private function activityChanges(array $attempt, array $settings, string $activity, string $now): array
    {
        $activity = max($activity, $attempt['client_activity_at']);
        $timed = $settings['timeLimitSec'] !== null;
        $deadline = PlayerStore::due($timed ? $attempt['started_at'] : $activity, $settings['timeLimitSec'] ?? 28800);
        $reason = $timed ? 'timer_expired' : 'stale_timeout';
        if ($settings['closesAt'] !== null && ($closing = PlayerStore::date($settings['closesAt'])) < $deadline) {
            $deadline = $closing; $reason = 'scheduled_close';
        }
        if ($activity >= $deadline) throw new PlayerException('invalid_timing');
        return ['client_activity_at' => $activity, 'last_activity_at' => $now, 'expires_at' => $deadline, 'deadline_reason' => $reason,
            'late_sync' => (int) ((bool) $attempt['late_sync'] || $attempt['status'] === 'abandoned' || $now >= $attempt['expires_at'])];
    }

    private function applyActivity(array $attempt, array $changes, string $now): array
    {
        if ($changes['expires_at'] > $now) $changes += ['status' => 'in_progress', 'finished_at' => null, 'ended_reason' => null, 'score' => null, 'percent' => null];
        $this->store->db->table('attempts')->where('id', $attempt['id'])->update($changes);
        $attempt = array_replace($attempt, $changes);
        return $changes['expires_at'] <= $now ? $this->abandon($attempt) : $attempt;
    }

    /** Five seconds permits clock/transport jitter; fixed answering deadlines are never extended. */
    private function clientTime(mixed $value, array $attempt, string $now): string
    {
        if (! is_string($value)) throw new PlayerException('invalid_timing');
        $date = PlayerStore::date($value);
        if ($date < $attempt['started_at'] || $date > PlayerStore::due($now, 5)) throw new PlayerException('invalid_timing');
        return $date;
    }

    private function questions(array $paper): array { return array_column($this->papers->definition($paper)['questions'], null, 'id'); }

    private function ack(array $attempt): array
    {
        return ['attemptId' => (string) $attempt['public_id'], 'status' => $attempt['status'],
            'expiresAt' => PlayerStore::iso($attempt['expires_at']), 'deadlineReason' => $attempt['deadline_reason'],
            'clientActivityAt' => PlayerStore::iso($attempt['client_activity_at']), 'lateSync' => (bool) $attempt['late_sync'], 'finishReason' => $attempt['ended_reason']];
    }

    private function serialize(array $attempt): array
    {
        $paper = $this->papers->findForAttempt($attempt);
        $document = $this->papers->studentDocument($paper, (string) $attempt['shuffle_seed']);
        $rows = $this->answerRows($attempt['id']);
        $questions = array_column($document['questions'], null, 'id');
        $document['questions'] = [];
        foreach ($rows as $row) {
            $question = $questions[(string) $row['question_id']];
            $options = array_column($question['options'], null, 'code');
            $question['options'] = array_values(array_map(static fn (string $code): array => $options[$code], $this->decodeCodes($row['presented_option_codes'])));
            $document['questions'][] = $question;
        }
        $activeAssigned = false;
        $items = [];
        foreach ($rows as $row) {
            $status = 'locked';
            if ($row['status'] === 'not_reached') {
                $status = ! $activeAssigned && $attempt['status'] === 'in_progress' ? 'active' : 'pending';
                $activeAssigned = true;
            }
            $items[] = [
                'questionId' => (string) $row['question_id'], 'status' => $status, 'answerStatus' => (string) $row['status'],
                'clientAnsweredAt' => PlayerStore::iso($row['client_answered_at']),
                'answerCodes' => $this->decodeCodes($row['selected_option_codes']), 'textAnswer' => (string) ($row['text_answer'] ?? ''),
            ];
        }
        return [
            'lateSync' => (bool) $attempt['late_sync'], 'clientActivityAt' => PlayerStore::iso($attempt['client_activity_at']),
            'mode' => 'assessment', 'attemptId' => (string) $attempt['public_id'], 'status' => (string) $attempt['status'],
            'startedAt' => PlayerStore::iso($attempt['started_at']), 'expiresAt' => PlayerStore::iso($attempt['expires_at']),
            'deadlineReason' => (string) $attempt['deadline_reason'], 'finishReason' => $attempt['ended_reason'],
            'student' => ['name' => (string) $attempt['name'], 'email' => $attempt['email'] === null ? null : (string) $attempt['email']],
            'quiz' => $document, 'items' => $items,
            'result' => $attempt['status'] === 'in_progress' ? null : $this->result($attempt, $rows, $this->papers->settings($paper)),
        ];
    }

    private function result(array $attempt, array $rows, array $settings): array
    {
        $result = ['confirmed' => $attempt['status'] === 'completed', 'timingPolicy' => $settings['timingPolicy'],
            'finishedAt' => PlayerStore::iso($attempt['finished_at']), 'items' => []];
        if ($settings['showScore']) {
            $result['score'] = ScoringService::score((int) $attempt['score']);
            $result['maxScore'] = ScoringService::score((int) $attempt['max_score']);
            $result['percent'] = number_format((float) $attempt['percent'], 2, '.', '');
        }
        if ($settings['showScore'] || $settings['showAnswers'] || $settings['showExplain']) foreach ($rows as $row) {
            $result['items'][] = ['questionId' => (string) $row['question_id'],
                'result' => $row['status'] === 'answered' ? ((bool) $row['is_correct'] ? 'correct' : 'wrong') : 'unanswered'];
        }
        return $result;
    }

    private function authorize(string $publicId, string $token): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $publicId) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) throw new PlayerException('invalid_credential', 401);
        $attempt = $this->store->db->table('attempts')->where('public_id', $publicId)->get()->getRowArray();
        if ($attempt === null || ! hash_equals((string) $attempt['token_hash'], hash('sha256', $token, true))) throw new PlayerException('invalid_credential', 401);
        return $attempt;
    }

    private function normalizedConfirmation(array $question, array $input): array
    {
        if ($input['status'] === 'skipped') {
            if (($input['answerCodes'] ?? null) !== [] || ($input['textAnswer'] ?? null) !== '') throw new PlayerException('invalid_answer');
            return ['answerCodes' => null, 'textAnswer' => null];
        }
        $answer = $this->scoring->answer($question, $input);
        if ($question['type'] === 'short_text') {
            if (ScoringService::normalize($answer['textAnswer']) === '') throw new PlayerException('invalid_answer');
            return ['answerCodes' => null, 'textAnswer' => $answer['textAnswer']];
        }
        if ($answer['answerCodes'] === []) throw new PlayerException('invalid_answer');
        return ['answerCodes' => $answer['answerCodes'], 'textAnswer' => null];
    }

    private function sameConfirmation(array $row, array $normalized): bool
    {
        return ($row['status'] === 'skipped') === ($normalized['answerCodes'] === null && $normalized['textAnswer'] === null)
            && $this->decodeCodes($row['selected_option_codes']) === ($normalized['answerCodes'] ?? [])
            && (string) ($row['text_answer'] ?? '') === (string) ($normalized['textAnswer'] ?? '');
    }

    private function answerRows(int|string $attemptId): array
    {
        return $this->store->db->table('attempt_answers')->where('attempt_id', $attemptId)->orderBy('pos')->get()->getResultArray();
    }

    private function decodeCodes(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        $decoded = is_array($value) ? $value : json_decode((string) $value, true, 32, JSON_THROW_ON_ERROR);
        return array_values(array_map('strval', is_array($decoded) ? $decoded : []));
    }

    private function hasExactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected) && array_diff(array_keys($value), $expected) === [];
    }
}
