<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

final class AdmissionService
{
    public function __construct(private readonly PlayerStore $store, private readonly Credentials $credentials,
        private readonly DefinitionService $definitions, private readonly PracticeService $practice) {}

    public function ticket(string $shareToken): array
    {
        $quiz = $this->store->db->table('quizzes')->where('share_token', $shareToken)->get()->getRowArray();
        if ($quiz === null) throw new PlayerException('not_found', 404);
        $this->available($quiz);
        $claims = ['quizId' => (string) $quiz['id'], 'shareToken' => $shareToken, 'version' => (int) $quiz['version'],
            'startKey' => bin2hex(random_bytes(16)), 'attemptId' => bin2hex(random_bytes(16)), 'seed' => bin2hex(random_bytes(16)),
            'settings' => $this->definitions->settings($quiz)];
        return ['ticket' => $this->credentials->sign('admission', $claims, 900), 'mode' => $quiz['mode'],
            // Store before POST: a lost start response can be recovered with the bearer, even after admission expiry.
            'attemptId' => $quiz['mode'] === 'assessment' ? $claims['attemptId'] : null,
            'credential' => $quiz['mode'] === 'assessment' ? $this->credentials->attemptToken($claims['attemptId'], $claims['startKey']) : null];
    }

    public function start(array $input, ?string $ip = null, ?string $agent = null): array
    {
        if (! is_string($input['ticket'] ?? null)) throw new PlayerException('invalid_credential', 401);
        $claims = $this->credentials->verify($input['ticket'], 'admission');
        return $this->store->transaction(function () use ($input, $claims, $ip, $agent): array {
            $quiz = $this->store->lock('quizzes', $claims['quizId']);
            $db = $this->store->db;
            $now = PlayerStore::now();
            $isPractice = $claims['settings']['mode'] === 'practice';
            if ($isPractice) {
                if (array_diff(array_keys($input), ['ticket']) !== []) throw new PlayerException('practice_anonymous');
                $existing = $db->table('practice_keys')->where('quiz_id', $quiz['id'])->where('request_key', $claims['startKey'])->get()->getRowArray();
            } else {
                $existing = $db->table('attempts')->where('quiz_id', $quiz['id'])->where('start_key', $claims['startKey'])->get()->getRowArray();
                $identity = $this->identity($input, $claims['settings']);
                $hash = hash('sha256', json_encode([$identity, $input['passcode'] ?? ''], JSON_THROW_ON_ERROR), true);
                if ($existing !== null) {
                    if (! hash_equals($existing['start_hash'], $hash)) throw new PlayerException('start_conflict', 409);
                    return ['mode' => 'assessment', 'attemptId' => $existing['public_id'], 'credential' => $this->credentials->attemptToken($existing['public_id'], $claims['startKey'])];
                }
            }
            if ($existing === null) {
                $this->available($quiz);
                if ((int) $quiz['version'] !== $claims['version']) throw new PlayerException('quiz_changed', 409);
                if (! $isPractice && $quiz['passcode_hash'] !== null
                    && (! is_string($input['passcode'] ?? null) || strlen($input['passcode']) > 72 || ! password_verify($input['passcode'], $quiz['passcode_hash']))) {
                    throw new PlayerException('wrong_passcode', 422, ['passcode' => lang('Player.errors.wrong_passcode')]);
                }
                $document = $this->definitions->document($quiz, $claims['settings'], $claims['seed']);
                $this->definitions->assertReady($document);
                if ($quiz['frozen_at'] === null) {
                    $db->table('quizzes')->where('id', $quiz['id'])->update(['frozen_at' => $now, 'version' => (int) $quiz['version'] + 1, 'updated_at' => $now]);
                }
            }
            if ($isPractice) {
                // Only expired keys, in bounded batches; no identity-based cache or rate-limit buckets.
                foreach ($db->table('practice_keys')->where('expires_at <', $now)->limit(100)->get()->getResultArray() as $expired) {
                    $db->table('practice_keys')->where('quiz_id', $expired['quiz_id'])->where('request_key', $expired['request_key'])->delete();
                }
                return $this->practice->start($quiz, $claims, $existing, $now);
            }
            $settings = $claims['settings'] + ['seed' => $claims['seed']];
            $maximum = array_sum(array_map(static fn (array $q): int => ScoringService::cents($q['points']), $document['questions']));
            $token = $this->credentials->attemptToken($claims['attemptId'], $claims['startKey']);
            $totalDue = PlayerStore::due($now, $settings['timeLimitSec']);
            $close = $settings['closesAt'] === null ? null : PlayerStore::date($settings['closesAt']);
            $db->table('attempts')->insert($identity + ['quiz_id' => $quiz['id'], 'revision' => $quiz['revision'], 'public_id' => $claims['attemptId'],
                'token_hash' => PlayerStore::binary(hash('sha256', $token, true)), 'start_key' => $claims['startKey'], 'start_hash' => PlayerStore::binary($hash),
                'ip' => $ip === null ? null : substr($ip, 0, 45), 'agent' => $agent === null ? null : mb_substr($agent, 0, 512),
                'status' => 'in_progress', 'phase' => 'answering', 'current_pos' => 1, 'version' => 1,
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR), 'started_at' => $now, 'total_due_at' => $totalDue,
                'close_at' => $close, 'due_at' => null, 'max_score' => ScoringService::decimal($maximum), 'updated_at' => $now, 'late_sync' => 0]);
            $attemptId = (string) $db->insertID();
            foreach ($document['questions'] as $index => $question) {
                $db->table('attempt_items')->insert(['attempt_id' => $attemptId, 'quiz_id' => $quiz['id'], 'question_id' => $question['id'], 'pos' => $index + 1,
                    'choice_order' => json_encode(array_column($question['options'], 'code'), JSON_THROW_ON_ERROR), 'status' => $index === 0 ? 'active' : 'pending',
                    'started_at' => $index === 0 ? $now : null, 'due_at' => $index === 0 ? PlayerStore::due($now, $question['timeLimitSec']) : null, 'save_ver' => 0]);
            }
            return ['mode' => 'assessment', 'attemptId' => $claims['attemptId'], 'credential' => $token];
        });
    }

    private function available(array $quiz): void
    {
        $owner = $this->store->db->table('users')->where('id', $quiz['user_id'])->where('active', 1)->where('deleted_at', null)->get()->getRowArray();
        if ($owner === null || $quiz['deleted_at'] !== null || in_array($quiz['status'], ['draft', 'archived'], true)) throw new PlayerException('not_found', 404);
        $now = PlayerStore::now();
        if ($quiz['status'] !== 'published' || ($quiz['closes_at'] !== null && $quiz['closes_at'] <= $now)) throw new PlayerException('quiz_closed', 409);
        if ($quiz['opens_at'] !== null && $quiz['opens_at'] > $now) throw new PlayerException('quiz_scheduled', 409);
    }

    private function identity(array $input, array $settings): array
    {
        $result = [];
        $fields = [];
        foreach (['name' => 120, 'email' => 254, 'phone' => 32] as $field => $limit) {
            $mode = $field === 'name' ? 'required' : $settings[$field . 'Mode'];
            if ($mode === 'hidden') { $result[$field] = null; continue; }
            $value = $input[$field] ?? '';
            if (! is_string($value) || mb_strlen($value) > $limit || ($mode === 'required' && trim($value) === '')
                || ($field === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false)
                || ($field === 'phone' && $value !== '' && ! preg_match('/^[+0-9() .-]{3,32}$/D', $value))) {
                $fields[$field] = lang('Player.errors.identity_invalid');
            } else { $result[$field] = trim($value) === '' ? null : trim($value); }
        }
        if ($fields !== []) throw new PlayerException('identity_invalid', 422, $fields);
        return $result;
    }
}
