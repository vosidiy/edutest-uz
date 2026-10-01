<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;
use App\Services\QuizPaperService;
use App\Services\QuizShareCode;
use DateTimeImmutable;
use DateTimeZone;

final class AdmissionService
{
    public function __construct(
        private readonly PlayerStore $store,
        private readonly Credentials $credentials,
        private readonly DefinitionService $definitions,
        private readonly PracticeService $practice,
        private readonly QuizPaperService $papers,
    ) {}

    /** @return array<string, mixed> */
    public function ticket(string $shareToken): array
    {
        if (! QuizShareCode::isValid($shareToken)) {
            throw new PlayerException('not_found', 404);
        }

        $quiz = $this->quizByShareCode($shareToken);
        $paper = $this->papers->currentPublished($quiz);
        $settings = $this->papers->settings($paper);
        $this->assertNewStartAvailable($quiz, $settings, PlayerStore::now());

        $claims = [
            'quizId'        => (string) $quiz['id'],
            'paperId'       => (string) $paper['public_id'],
            'paperRevision' => (int) $paper['revision'],
            'startKey'      => bin2hex(random_bytes(16)),
            'seed'          => bin2hex(random_bytes(16)),
        ];

        return [
            'ticket'        => $this->credentials->sign('admission', $claims, 900),
            'mode'          => $settings['mode'],
            'paperRevision' => (int) $paper['revision'],
        ];
    }

    /** @return array<string, mixed> */
    public function start(array $input, ?string $ip = null, ?string $agent = null): array
    {
        if (! is_string($input['ticket'] ?? null)) {
            throw new PlayerException('invalid_credential', 401);
        }
        $claims = $this->credentials->verify($input['ticket'], 'admission');
        $this->assertClaims($claims);

        return $this->store->transaction(function () use ($claims, $input, $ip, $agent): array {
            $quiz = $this->store->lock('quizzes', (string) $claims['quizId']);

            // A successful start is retry-safe. Identity and passcode are validated only for a new run.
            $existingAttempt = $this->store->db->table('attempts')
                ->where('quiz_id', $quiz['id'])->where('start_key', $claims['startKey'])
                ->get()->getRowArray();
            if ($existingAttempt !== null) {
                return $this->assessmentStartResult($existingAttempt, (string) $claims['startKey']);
            }

            $existingPractice = $this->store->db->table('practice_keys')
                ->where('quiz_id', $quiz['id'])->where('request_key', $claims['startKey'])
                ->get()->getRowArray();
            if ($existingPractice !== null) {
                $paper = $this->papers->findForPractice((string) $claims['paperId'], (string) $quiz['id']);
                return $this->practice->start($quiz, $paper, $claims, $existingPractice, PlayerStore::now());
            }

            $paper = $this->papers->currentPublished($quiz);
            if (! hash_equals((string) $paper['public_id'], (string) $claims['paperId'])
                || (int) $paper['revision'] !== (int) $claims['paperRevision']) {
                throw new PlayerException('quiz_changed', 409);
            }
            $settings = $this->papers->settings($paper);
            $now = PlayerStore::now();
            $this->assertNewStartAvailable($quiz, $settings, $now);
            $document = $this->papers->studentDocument($paper, (string) $claims['seed']);
            $this->definitions->assertReady($document);

            if ($settings['mode'] === 'practice') {
                return $this->practice->start($quiz, $paper, $claims, null, $now);
            }

            $identity = $this->identity($input, $settings);
            if ($paper['passcode_hash'] !== null
                && (! is_string($input['passcode'] ?? null) || ! password_verify($input['passcode'], (string) $paper['passcode_hash']))) {
                throw new PlayerException('wrong_passcode', 422, ['passcode' => lang('Player.errors.wrong_passcode')]);
            }

            [$expiresAt, $deadlineReason] = $this->deadline($now, $settings);
            $attemptPublicId = bin2hex(random_bytes(16));
            $token = $this->credentials->attemptToken($attemptPublicId, (string) $claims['startKey']);
            $attempt = [
                'quiz_id'         => $quiz['id'],
                'paper_id'        => $paper['id'],
                'public_id'       => $attemptPublicId,
                'token_hash'      => PlayerStore::binary(hash('sha256', $token, true)),
                'start_key'       => $claims['startKey'],
                'shuffle_seed'    => $claims['seed'],
                'name'            => $identity['name'],
                'email'           => $identity['email'],
                'phone'           => $identity['phone'],
                'ip'              => $this->nullableLimited($ip, 45),
                'agent'           => $this->nullableLimited($agent, 512),
                'status'          => 'in_progress',
                'started_at'      => $now,
                'last_activity_at'=> $now,
                'client_activity_at' => $now,
                'late_sync'       => 0,
                'expires_at'      => $expiresAt,
                'deadline_reason' => $deadlineReason,
                'finished_at'     => null,
                'ended_reason'    => null,
                'score'           => null,
                'max_score'       => count($document['questions']),
                'percent'         => null,
            ];
            if (! $this->store->db->table('attempts')->insert($attempt)) {
                throw new PlayerException('server_error', 500);
            }
            $attemptId = (string) $this->store->db->insertID();
            foreach ($document['questions'] as $position => $question) {
                $codes = array_values(array_map(static fn (array $option): string => (string) $option['code'], $question['options']));
                if (! $this->store->db->table('attempt_answers')->insert([
                    'attempt_id'             => $attemptId,
                    'question_id'            => $question['id'],
                    'pos'                    => $position + 1,
                    'presented_option_codes' => json_encode($codes, JSON_THROW_ON_ERROR),
                    'status'                 => 'not_reached',
                    'selected_option_codes'  => null,
                    'text_answer'            => null,
                    'is_correct'             => null,
                    'answered_at'            => null,
                ])) {
                    throw new PlayerException('server_error', 500);
                }
            }

            return [
                'mode'       => 'assessment',
                'attemptId'  => $attemptPublicId,
                'credential' => $token,
            ];
        });
    }

    /** @return array<string, mixed> */
    private function assessmentStartResult(array $attempt, string $startKey): array
    {
        $token = $this->credentials->attemptToken((string) $attempt['public_id'], $startKey);
        if (! hash_equals((string) $attempt['token_hash'], hash('sha256', $token, true))) {
            throw new PlayerException('invalid_credential', 401);
        }
        return ['mode' => 'assessment', 'attemptId' => (string) $attempt['public_id'], 'credential' => $token];
    }

    /** @return array{name:string,email:?string,phone:?string} */
    private function identity(array $input, array $settings): array
    {
        $fields = [];
        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        if ($name === '' || mb_strlen($name) > 120) {
            $fields['name'] = lang('Player.errors.identity_invalid');
        }

        $email = $this->identityValue($input, 'email', (string) $settings['emailMode'], 254, $fields);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fields['email'] = lang('Player.errors.identity_invalid');
        }
        $phone = $this->identityValue($input, 'phone', (string) $settings['phoneMode'], 32, $fields);

        if ($fields !== []) {
            throw new PlayerException('identity_invalid', 422, $fields);
        }
        return ['name' => $name, 'email' => $email, 'phone' => $phone];
    }

    private function identityValue(array $input, string $field, string $mode, int $max, array &$fields): ?string
    {
        if ($mode === 'hidden') return null;
        $value = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
        if (($mode === 'required' && $value === '') || mb_strlen($value) > $max) {
            $fields[$field] = lang('Player.errors.identity_invalid');
        }
        return $value === '' ? null : $value;
    }

    /** @return array{string,string} */
    private function deadline(string $startedAt, array $settings): array
    {
        $reason = $settings['timeLimitSec'] === null ? 'stale_timeout' : 'timer_expired';
        $deadline = PlayerStore::due($startedAt, $settings['timeLimitSec'] ?? 28800);
        $closing = $settings['closesAt'] === null ? null : PlayerStore::date((string) $settings['closesAt']);
        if ($closing !== null && strcmp($closing, (string) $deadline) < 0) {
            return [$closing, 'scheduled_close'];
        }
        return [(string) $deadline, $reason];
    }

    private function assertNewStartAvailable(array $quiz, array $settings, string $now): void
    {
        if ($quiz['deleted_at'] !== null || $quiz['status'] !== 'published') {
            throw new PlayerException('quiz_closed', 410);
        }
        $opens = $settings['opensAt'] === null ? null : PlayerStore::date((string) $settings['opensAt']);
        $closes = $settings['closesAt'] === null ? null : PlayerStore::date((string) $settings['closesAt']);
        if ($opens !== null && strcmp($now, $opens) < 0) throw new PlayerException('quiz_scheduled', 409);
        if ($closes !== null && strcmp($now, $closes) >= 0) throw new PlayerException('quiz_closed', 410);
    }

    /** @return array<string, mixed> */
    private function quizByShareCode(string $shareToken): array
    {
        $quiz = $this->store->db->table('quizzes')->where('share_token', $shareToken)->get()->getRowArray();
        if ($quiz === null || $quiz['deleted_at'] !== null || ! in_array($quiz['status'], ['published'], true)) {
            throw new PlayerException('not_found', 404);
        }
        return $quiz;
    }

    private function assertClaims(array $claims): void
    {
        if (! ctype_digit((string) ($claims['quizId'] ?? ''))
            || ! is_string($claims['paperId'] ?? null) || preg_match('/^[a-f0-9]{32}$/D', $claims['paperId']) !== 1
            || ! is_int($claims['paperRevision'] ?? null)
            || ! is_string($claims['startKey'] ?? null) || preg_match('/^[a-f0-9]{32}$/D', $claims['startKey']) !== 1
            || ! is_string($claims['seed'] ?? null) || preg_match('/^[a-f0-9]{32}$/D', $claims['seed']) !== 1) {
            throw new PlayerException('invalid_credential', 401);
        }
    }

    private function nullableLimited(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
