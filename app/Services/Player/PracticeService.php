<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

final class PracticeService
{
    public function __construct(private readonly PlayerStore $store, private readonly Credentials $credentials, private readonly DefinitionService $definitions) {}

    /** Called under the quiz lock; the only persisted per-start data is the expiring dedup key. */
    public function start(array $quiz, array $claims, ?array $existing, string $now): array
    {
        if ($existing === null) {
            $this->store->db->table('practice_keys')->insert(['quiz_id' => $quiz['id'], 'request_key' => $claims['startKey'], 'expires_at' => PlayerStore::due($now, 86400)]);
            $this->store->db->table('quizzes')->where('id', $quiz['id'])->set('practice_starts', 'practice_starts + 1', false)->update();
        } else {
            $now = (new \DateTimeImmutable($existing['expires_at'], new \DateTimeZone('UTC')))->modify('-86400 seconds')->format('Y-m-d H:i:s.u');
        }
        $ticketClaims = ['quizId' => (string) $quiz['id'], 'revision' => (int) $quiz['revision'], 'settings' => $claims['settings'], 'seed' => $claims['seed'], 'startedAt' => PlayerStore::iso($now)];
        return ['mode' => 'practice', 'credential' => $this->credentials->sign('practice', $ticketClaims, 86400),
            'startedAt' => PlayerStore::iso($now), 'totalDueAt' => PlayerStore::iso(PlayerStore::due($now, $claims['settings']['timeLimitSec'])),
            'closeAt' => $claims['settings']['closesAt'], 'quiz' => $this->definitions->document($quiz, $claims['settings'], $claims['seed'])];
    }

    public function media(string $credential): array
    {
        $claims = $this->credentials->verify($credential, 'practice');
        $quiz = $this->store->db->table('quizzes')->where('id', $claims['quizId'])->get()->getRowArray();
        if ($quiz === null || (int) $quiz['revision'] !== $claims['revision'] || $quiz['frozen_at'] === null) throw new PlayerException('not_found', 404);
        return ['credential' => $this->credentials->sign('practice', $claims, 86400),
            'quiz' => $this->definitions->document($quiz, $claims['settings'], $claims['seed'])];
    }
}
