<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;
use App\Services\QuizPaperService;

final class PracticeService
{
    public function __construct(private readonly PlayerStore $store, private readonly Credentials $credentials, private readonly QuizPaperService $papers) {}

    /** Called under the quiz lock; the only per-run persisted data is the expiring dedup key. */
    public function start(array $quiz, array $paper, array $claims, ?array $existing, string $now): array
    {
        if ($existing === null) {
            $this->store->db->table('practice_keys')->insert(['quiz_id' => $quiz['id'], 'paper_id' => $paper['id'],
                'request_key' => $claims['startKey'], 'expires_at' => PlayerStore::due($now, 86400)]);
            $this->store->db->table('quizzes')->where('id', $quiz['id'])->set('practice_starts', 'practice_starts + 1', false)->update();
        } else {
            $now = (new \DateTimeImmutable($existing['expires_at'], new \DateTimeZone('UTC')))->modify('-86400 seconds')->format('Y-m-d H:i:s.u');
        }
        $settings = $this->papers->settings($paper);
        [$expiresAt, $deadlineReason] = $this->deadline($now, $settings);
        $ticketClaims = ['quizId' => (string) $quiz['id'], 'paperId' => (string) $paper['public_id'],
            'requestKey' => (string) $claims['startKey'],
            'seed' => $claims['seed'], 'startedAt' => PlayerStore::iso($now)];
        return ['mode' => 'practice', 'credential' => $this->credentials->sign('practice', $ticketClaims, 86400),
            'status' => 'in_progress', 'startedAt' => PlayerStore::iso($now),
            'expiresAt' => PlayerStore::iso($expiresAt), 'deadlineReason' => $deadlineReason,
            'quiz' => $this->papers->studentDocument($paper, (string) $claims['seed'])];
    }

    public function media(string $credential): array
    {
        $claims = $this->credentials->verify($credential, 'practice');
        if (! is_string($claims['requestKey'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $claims['requestKey'])) {
            throw new PlayerException('invalid_credential', 401);
        }
        return $this->store->transaction(function () use ($claims): array {
            $paper = $this->papers->findForPractice((string) $claims['paperId'], (string) $claims['quizId']);
            $now = PlayerStore::now();
            $this->store->db->table('practice_keys')
                ->where('quiz_id', $claims['quizId'])
                ->where('paper_id', $paper['id'])
                ->where('request_key', $claims['requestKey'])
                ->where('expires_at >=', $now)
                ->update(['expires_at' => PlayerStore::due($now, 86400)]);
            if ($this->store->db->affectedRows() !== 1) {
                throw new PlayerException('invalid_credential', 401);
            }
            $settings = $this->papers->settings($paper);
            [$expiresAt, $deadlineReason] = $this->deadline(PlayerStore::date((string) $claims['startedAt']), $settings);
            return ['credential' => $this->credentials->sign('practice', $claims, 86400),
                'expiresAt' => PlayerStore::iso($expiresAt), 'deadlineReason' => $deadlineReason,
                'quiz' => $this->papers->studentDocument($paper, (string) $claims['seed'])];
        });
    }

    /** @return array{string,string} */
    private function deadline(string $startedAt, array $settings): array
    {
        $reason = $settings['timeLimitSec'] === null ? 'stale_timeout' : 'timer_expired';
        $deadline = (string) PlayerStore::due($startedAt, $settings['timeLimitSec'] ?? 28800);
        $closing = $settings['closesAt'] === null ? null : PlayerStore::date((string) $settings['closesAt']);
        return $closing !== null && strcmp($closing, $deadline) < 0
            ? [$closing, 'scheduled_close']
            : [$deadline, $reason];
    }
}
