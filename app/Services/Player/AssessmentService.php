<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;
use App\Services\AttemptSnapshotService;

final class AssessmentService
{
    public function __construct(
        private readonly PlayerStore $store,
        private readonly AttemptSnapshotService $snapshots,
        private readonly ScoringService $scoring,
    ) {}

    /** @return array<string, mixed> */
    public function load(string $publicId, string $token): array
    {
        $authorized = $this->authorize($publicId, $token);

        return $this->mutate($authorized, fn (array $attempt): array => $this->serialize($attempt));
    }

    /** @return array<string, mixed> */
    public function progress(string $publicId, string $token, array $input): array
    {
        $authorized = $this->authorize($publicId, $token);

        return $this->mutate($authorized, function (array $attempt) use ($input): array {
            if ($attempt['status'] !== 'in_progress') {
                throw new PlayerException('attempt_finalized', 409);
            }
            if (! $this->hasExactKeys($input, ['answerVersion', 'answers'])
                || ! is_int($input['answerVersion'])
                || $input['answerVersion'] < 1
                || $input['answerVersion'] > 4294967295) {
                throw new PlayerException('invalid_progress');
            }

            $snapshot = $this->snapshots->decode($attempt['snapshot']);
            $answers = $this->validateAnswers($input['answers'], $snapshot['questions'], $attempt, false);
            $version = (int) $attempt['answer_version'];
            $stored = $this->decodeAnswers($attempt['answers']);

            if ($input['answerVersion'] < $version) {
                throw new PlayerException('stale_progress', 409);
            }
            if ($input['answerVersion'] === $version) {
                if ($answers !== $stored) {
                    throw new PlayerException('progress_conflict', 409);
                }

                return $this->ack($attempt);
            }

            $now = PlayerStore::now();
            $changes = [
                'answers'        => $this->encodeAnswers($answers),
                'answer_version' => $input['answerVersion'],
                'last_saved_at'  => $now,
            ];
            $this->store->db->table('attempts')->where('id', $attempt['id'])->update($changes);

            return $this->ack(array_replace($attempt, $changes));
        });
    }

    /** @return array<string, mixed> */
    public function finish(string $publicId, string $token, array $input): array
    {
        $authorized = $this->authorize($publicId, $token);

        return $this->store->transaction(function () use ($authorized, $input): array {
            $attempt = $this->store->lock('attempts', (string) $authorized['id']);

            if (! $this->hasExactKeys($input, ['answerVersion', 'answers', 'finishReason', 'clientFinishedAt'])
                || ! is_int($input['answerVersion'])
                || $input['answerVersion'] < 1
                || $input['answerVersion'] > 4294967295
                || ! in_array($input['finishReason'] ?? null, ['completed', 'exited', 'expired'], true)
                || ! is_string($input['clientFinishedAt'] ?? null)) {
                throw new PlayerException('invalid_progress');
            }

            $snapshot = $this->snapshots->decode($attempt['snapshot']);
            $answers = $this->validateAnswers($input['answers'], $snapshot['questions'], $attempt, true);
            $finishedAt = $this->clientTime($input['clientFinishedAt'], $attempt);
            $reason = (string) $input['finishReason'];
            $this->assertFinishTiming($reason, $finishedAt, $attempt, $answers);

            $stored = $this->decodeAnswers($attempt['answers']);
            if ($attempt['status'] === 'completed') {
                if ((int) $attempt['answer_version'] !== $input['answerVersion']
                    || $stored !== $answers
                    || $attempt['ended_reason'] !== $reason
                    || $attempt['finished_at'] !== $finishedAt) {
                    throw new PlayerException('finish_conflict', 409);
                }

                return $this->serialize($attempt);
            }

            $storedVersion = (int) $attempt['answer_version'];
            if ($input['answerVersion'] < $storedVersion
                || ($input['answerVersion'] === $storedVersion && ! $this->isPrefix($stored, $answers))) {
                throw new PlayerException('finish_conflict', 409);
            }

            [$score, $items] = $this->grade($snapshot['questions'], $answers);
            $now = PlayerStore::now();
            $changes = [
                'answers'        => $this->encodeAnswers($answers),
                'answer_version' => $input['answerVersion'],
                'status'         => 'completed',
                'last_saved_at'  => $now,
                'finished_at'    => $finishedAt,
                'ended_reason'   => $reason,
                'late_sync'      => (int) ((bool) $attempt['late_sync'] || $attempt['status'] === 'abandoned' || $now >= $attempt['expires_at']),
                'score'          => $score,
                'percent'        => ScoringService::percent($score, (int) $attempt['max_score']),
            ];
            $this->store->db->table('attempts')->where('id', $attempt['id'])->update($changes);
            $attempt = array_replace($attempt, $changes);

            return $this->serialize($attempt, $items);
        });
    }

    /** @return array<string, mixed> */
    public function results(string $publicId, string $token): array
    {
        $data = $this->load($publicId, $token);
        if ($data['result'] === null) {
            throw new PlayerException('not_finished', 409);
        }

        return $data;
    }

    /** Renew attempt-bound media URLs. */
    public function media(string $publicId, string $token): array
    {
        return $this->load($publicId, $token);
    }

    public function resolveQuizOverdue(int $quizId, int $limit = 250): void
    {
        $ids = array_column(
            $this->store->db->table('attempts')->select('id')
                ->where('quiz_id', $quizId)
                ->where('status', 'in_progress')
                ->where('expires_at <=', PlayerStore::now())
                ->orderBy('expires_at')
                ->limit(max(1, min(1000, $limit)))
                ->get()->getResultArray(),
            'id',
        );

        foreach ($ids as $id) {
            $this->store->transaction(function () use ($id): void {
                $this->resolveExpiryLocked($this->store->lock('attempts', (string) $id));
            });
        }
    }

    /** @return array<string, mixed> */
    private function serialize(array $attempt, ?array $gradedItems = null): array
    {
        $snapshot = $this->snapshots->decode($attempt['snapshot']);
        $answers = $this->decodeAnswers($attempt['answers']);
        $data = $this->ack($attempt) + [
            'startedAt' => PlayerStore::iso($attempt['started_at']),
            'student'   => [
                'name'  => (string) $attempt['name'],
                'email' => $attempt['email'] === null ? null : (string) $attempt['email'],
                'phone' => $attempt['phone'] === null ? null : (string) $attempt['phone'],
            ],
            'quiz'      => $this->snapshots->studentDocument($attempt),
            'answers'   => $answers,
            'result'    => null,
        ];

        if ($attempt['status'] !== 'in_progress') {
            if ($gradedItems === null) {
                [, $gradedItems] = $this->grade($snapshot['questions'], $this->completeForResult($answers, $snapshot['questions']));
            }
            $data['result'] = $this->result($attempt, $gradedItems, $snapshot);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function ack(array $attempt): array
    {
        return [
            'attemptId'    => (string) $attempt['public_id'],
            'status'       => (string) $attempt['status'],
            'answerVersion'=> (int) $attempt['answer_version'],
            'lastSavedAt'  => PlayerStore::iso($attempt['last_saved_at']),
            'expiresAt'    => PlayerStore::iso($attempt['expires_at']),
            'finishedAt'   => PlayerStore::iso($attempt['finished_at']),
            'finishReason' => $attempt['ended_reason'],
            'lateSync'     => (bool) $attempt['late_sync'],
        ];
    }

    /** @return array<string, mixed> */
    private function result(array $attempt, array $items, array $snapshot): array
    {
        $settings = $snapshot['quiz'];
        $result = [
            'official'    => $attempt['status'] === 'completed',
            'provisional' => $attempt['status'] === 'abandoned',
            'finishedAt'  => PlayerStore::iso($attempt['finished_at']),
            'finishReason'=> $attempt['ended_reason'],
            'lateSync'    => (bool) $attempt['late_sync'],
            'items'       => [],
        ];

        if ((bool) $settings['showScore']) {
            $result['score'] = (int) $attempt['score'];
            $result['maxScore'] = (int) $attempt['max_score'];
            $result['percent'] = number_format((float) $attempt['percent'], 2, '.', '');
        }
        if ((bool) $settings['showScore'] || (bool) $settings['showAnswers']) {
            foreach ($items as $item) {
                $view = [
                    'questionId' => $item['questionId'],
                    'result'     => $item['result'],
                ];
                if ((bool) $settings['showAnswers']) {
                    $view['answerCode'] = $item['answerCode'];
                    $view['correctCode'] = $item['correctCode'];
                }
                $result['items'][] = $view;
            }
        }

        return $result;
    }

    /** @return list<array{questionId:string,answerCode:?string,answeredAt:?string}> */
    private function validateAnswers(mixed $value, array $questions, array $attempt, bool $final): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > count($questions)
            || ($final && count($value) !== count($questions))) {
            throw new PlayerException('invalid_progress');
        }

        $answers = [];
        $previousTime = null;
        $unreached = false;
        foreach ($value as $index => $item) {
            if (! is_array($item) || ! $this->hasExactKeys($item, ['questionId', 'answerCode', 'answeredAt'])
                || ! is_string($item['questionId'] ?? null)
                || $item['questionId'] !== (string) $questions[$index]['id']) {
                throw new PlayerException('invalid_progress');
            }

            $code = $this->scoring->validateCode($questions[$index], $item['answerCode'] ?? null);
            $answeredAt = $item['answeredAt'] ?? null;
            if ($answeredAt === null) {
                if (! $final || $code !== null) {
                    throw new PlayerException('invalid_progress');
                }
                $unreached = true;
            } else {
                if ($unreached || ! is_string($answeredAt)) {
                    throw new PlayerException('invalid_progress');
                }
                $databaseTime = PlayerStore::date($answeredAt);
                if ($databaseTime < $attempt['started_at']
                    || $databaseTime > $attempt['expires_at']
                    || ($previousTime !== null && $databaseTime < $previousTime)) {
                    throw new PlayerException('invalid_timing');
                }
                $previousTime = $databaseTime;
                $answeredAt = PlayerStore::iso($databaseTime);
            }

            $answers[] = [
                'questionId' => (string) $item['questionId'],
                'answerCode' => $code,
                'answeredAt' => $answeredAt,
            ];
        }

        return $answers;
    }

    private function assertFinishTiming(string $reason, string $finishedAt, array $attempt, array $answers): void
    {
        if ($reason === 'completed') {
            foreach ($answers as $answer) {
                if ($answer['answeredAt'] === null) {
                    throw new PlayerException('invalid_progress');
                }
            }
        }
        if ($finishedAt < $attempt['started_at']) {
            throw new PlayerException('invalid_timing');
        }
        if ($reason === 'expired') {
            if ($finishedAt < $attempt['expires_at']) {
                throw new PlayerException('invalid_timing');
            }
        } elseif ($finishedAt > $attempt['expires_at']) {
            throw new PlayerException('invalid_timing');
        }

        foreach ($answers as $answer) {
            if ($answer['answeredAt'] !== null && PlayerStore::date($answer['answeredAt']) > $finishedAt) {
                throw new PlayerException('invalid_timing');
            }
        }
    }

    private function clientTime(string $value, array $attempt): string
    {
        $time = PlayerStore::date($value);
        if ($time > PlayerStore::due(PlayerStore::now(), 5)) {
            throw new PlayerException('invalid_timing');
        }

        return $time;
    }

    /** @return array{0:int,1:list<array{questionId:string,answerCode:?string,correctCode:string,result:string}>} */
    private function grade(array $questions, array $answers): array
    {
        $score = 0;
        $items = [];
        foreach ($questions as $index => $question) {
            $answer = $answers[$index] ?? ['questionId' => (string) $question['id'], 'answerCode' => null, 'answeredAt' => null];
            $correct = $this->scoring->isCorrect($question, $answer['answerCode']);
            if ($correct) {
                $score++;
            }
            $items[] = [
                'questionId' => (string) $question['id'],
                'answerCode' => $answer['answerCode'],
                'correctCode'=> (string) $question['correctCode'],
                'result'     => $answer['answeredAt'] === null || $answer['answerCode'] === null
                    ? 'skipped'
                    : ($correct ? 'correct' : 'wrong'),
            ];
        }

        return [$score, $items];
    }

    /** Fill a provisional prefix with an unanswered tail for grading/reporting. */
    private function completeForResult(array $answers, array $questions): array
    {
        foreach (array_slice($questions, count($answers)) as $question) {
            $answers[] = ['questionId' => (string) $question['id'], 'answerCode' => null, 'answeredAt' => null];
        }

        return $answers;
    }

    private function resolveExpiryLocked(array $attempt): array
    {
        if ($attempt['status'] !== 'in_progress' || PlayerStore::now() < $attempt['expires_at']) {
            return $attempt;
        }

        $snapshot = $this->snapshots->decode($attempt['snapshot']);
        [$score] = $this->grade(
            $snapshot['questions'],
            $this->completeForResult($this->decodeAnswers($attempt['answers']), $snapshot['questions']),
        );
        $changes = [
            'status'       => 'abandoned',
            'finished_at'  => $attempt['expires_at'],
            'ended_reason' => 'expired',
            'score'        => $score,
            'percent'      => ScoringService::percent($score, (int) $attempt['max_score']),
        ];
        $this->store->db->table('attempts')->where('id', $attempt['id'])->update($changes);

        return array_replace($attempt, $changes);
    }

    /** Resolve lazy expiry in a transaction, even when the requested operation then fails. */
    private function mutate(array $authorized, callable $operation): array
    {
        $result = $this->store->transaction(function () use ($authorized, $operation): array|PlayerException {
            $attempt = $this->resolveExpiryLocked($this->store->lock('attempts', (string) $authorized['id']));
            try {
                return $operation($attempt);
            } catch (PlayerException $error) {
                return $error;
            }
        });
        if ($result instanceof PlayerException) {
            throw $result;
        }

        return $result;
    }

    private function authorize(string $publicId, string $token): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $publicId) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw new PlayerException('invalid_credential', 401);
        }
        $attempt = $this->store->db->table('attempts')->where('public_id', $publicId)->get()->getRowArray();
        if ($attempt === null
            || ! hash_equals((string) $attempt['token_hash'], hash('sha256', $token, true))) {
            throw new PlayerException('invalid_credential', 401);
        }

        return $attempt;
    }

    /** @return list<array<string, mixed>> */
    private function decodeAnswers(array|string $value): array
    {
        $answers = is_array($value) ? $value : json_decode($value, true);
        if (! is_array($answers) || ! array_is_list($answers)) {
            throw new PlayerException('server_error', 500);
        }

        return $answers;
    }

    private function encodeAnswers(array $answers): string
    {
        return json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function isPrefix(array $prefix, array $complete): bool
    {
        return count($prefix) <= count($complete)
            && $prefix === array_slice($complete, 0, count($prefix));
    }

    private function hasExactKeys(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }
}
