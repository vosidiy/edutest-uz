<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

final class PracticeKeyModel
{
    public const TABLE = 'practice_keys';

    /** @var list<string> */
    public const KEY_FIELDS = ['quiz_id', 'request_key'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function exists(int|string $quizId, string $requestKey): bool
    {
        return $this->db->table(self::TABLE)
            ->where('quiz_id', $quizId)
            ->where('request_key', $requestKey)
            ->countAllResults() > 0;
    }

    public function insert(int|string $quizId, string $requestKey, string $expiresAt): bool
    {
        return $this->db->table(self::TABLE)->insert([
            'quiz_id'     => $quizId,
            'request_key' => $requestKey,
            'expires_at'  => $expiresAt,
        ]);
    }

    public function delete(int|string $quizId, string $requestKey): bool
    {
        return $this->db->table(self::TABLE)
            ->where('quiz_id', $quizId)
            ->where('request_key', $requestKey)
            ->delete();
    }
}
