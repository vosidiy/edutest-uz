<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;
use App\Services\AttemptSnapshotService;
use App\Services\QuizShareCode;

final class AdmissionService
{
    public function __construct(
        private readonly PlayerStore $store,
        private readonly Credentials $credentials,
        private readonly DefinitionService $definitions,
        private readonly AttemptSnapshotService $snapshots,
    ) {}

    /** @return array<string, mixed> */
    public function ticket(string $shareToken): array
    {
        if (! QuizShareCode::isValid($shareToken)) {
            throw new PlayerException('not_found', 404);
        }

        $quiz = $this->store->db->table('quizzes')
            ->where('share_token', $shareToken)
            ->where('status', 'published')
            ->where('deleted_at', null)
            ->get()->getRowArray();
        if ($quiz === null) {
            throw new PlayerException('not_found', 404);
        }

        $claims = [
            'quizId'      => (string) $quiz['id'],
            'quizVersion' => (int) $quiz['version'],
            'startKey'    => bin2hex(random_bytes(16)),
        ];

        return [
            'ticket'      => $this->credentials->sign('admission', $claims, 900),
            'quizVersion' => (int) $quiz['version'],
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

            // A successful start replay wins over later quiz edits, Draft status, or deletion.
            $existing = $this->store->db->table('attempts')
                ->where('quiz_id', $quiz['id'])
                ->where('start_key', $claims['startKey'])
                ->get()->getRowArray();
            if ($existing !== null) {
                return $this->startResult($existing, (string) $claims['startKey']);
            }

            if ($quiz['deleted_at'] !== null || $quiz['status'] !== 'published') {
                throw new PlayerException('quiz_unpublished', 409);
            }
            if ((int) $quiz['version'] !== (int) $claims['quizVersion']) {
                throw new PlayerException('quiz_changed', 409);
            }

            $identity = $this->identity($input, $quiz);
            $snapshot = $this->snapshots->capture($quiz);
            $this->definitions->assertReady($snapshot);

            $now = PlayerStore::now();
            $publicId = bin2hex(random_bytes(16));
            $token = $this->credentials->attemptToken($publicId, (string) $claims['startKey']);
            $attempt = [
                'quiz_id'       => $quiz['id'],
                'public_id'     => $publicId,
                'token_hash'    => PlayerStore::binary(hash('sha256', $token, true)),
                'start_key'     => $claims['startKey'],
                'name'          => $identity['name'],
                'email'         => $identity['email'],
                'phone'         => $identity['phone'],
                'ip'            => $this->limited($ip, 45),
                'agent'         => $this->limited($agent, 512),
                'snapshot'      => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'answers'       => '[]',
                'answer_version'=> 0,
                'status'        => 'in_progress',
                'started_at'    => $now,
                'last_saved_at' => $now,
                'expires_at'    => PlayerStore::due($now, (int) $quiz['time_limit_sec']),
                'finished_at'   => null,
                'ended_reason'  => null,
                'late_sync'     => 0,
                'score'         => null,
                'max_score'     => count($snapshot['questions']),
                'percent'       => null,
            ];

            if (! $this->store->db->table('attempts')->insert($attempt)) {
                throw new PlayerException('server_error', 500);
            }

            return ['attemptId' => $publicId, 'credential' => $token];
        });
    }

    /** @return array{attemptId:string,credential:string} */
    private function startResult(array $attempt, string $startKey): array
    {
        $token = $this->credentials->attemptToken((string) $attempt['public_id'], $startKey);
        if (! hash_equals((string) $attempt['token_hash'], hash('sha256', $token, true))) {
            throw new PlayerException('invalid_credential', 401);
        }

        return ['attemptId' => (string) $attempt['public_id'], 'credential' => $token];
    }

    /** @return array{name:string,email:?string,phone:?string} */
    private function identity(array $input, array $quiz): array
    {
        $fields = [];
        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        if ($name === '' || mb_strlen($name) > 120) {
            $fields['name'] = lang('Player.errors.identity_invalid');
        }

        $email = $this->optionalIdentity($input, 'email', (bool) $quiz['ask_email'], 254, $fields);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fields['email'] = lang('Player.errors.identity_invalid');
        }
        $phone = $this->optionalIdentity($input, 'phone', (bool) $quiz['ask_phone'], 32, $fields);

        if ($fields !== []) {
            throw new PlayerException('identity_invalid', 422, $fields);
        }

        return ['name' => $name, 'email' => $email, 'phone' => $phone];
    }

    private function optionalIdentity(array $input, string $field, bool $enabled, int $max, array &$fields): ?string
    {
        if (! $enabled) {
            return null;
        }
        $value = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
        if (mb_strlen($value) > $max) {
            $fields[$field] = lang('Player.errors.identity_invalid');
        }

        return $value === '' ? null : $value;
    }

    private function assertClaims(array $claims): void
    {
        if (count($claims) !== 3
            || ! ctype_digit((string) ($claims['quizId'] ?? ''))
            || ! is_int($claims['quizVersion'] ?? null)
            || $claims['quizVersion'] < 1
            || ! is_string($claims['startKey'] ?? null)
            || preg_match('/^[a-f0-9]{32}$/D', $claims['startKey']) !== 1) {
            throw new PlayerException('invalid_credential', 401);
        }
    }

    private function limited(?string $value, int $maximum): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $maximum);
    }
}
