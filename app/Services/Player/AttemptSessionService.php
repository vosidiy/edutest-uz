<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Services\QuizShareCode;

/** Resolves the per-quiz HttpOnly cookie to its matching assessment attempt. */
final class AttemptSessionService
{
    public const GRACE_SECONDS = 86400;
    private const COOKIE_PREFIX = 'edutest_attempt_';

    // Wire the database and collaborating services used by this class.
    public function __construct(private readonly PlayerStore $store)
    {
    }

    // Name the per-quiz attempt cookie.
    public function cookieName(string $shareToken): string
    {
        return self::COOKIE_PREFIX . $shareToken;
    }

    // Find the owning quiz before reading its attempt cookie.
    public function cookieNameForAttempt(string $publicId): ?string
    {
        if (! $this->validPublicId($publicId)) {
            return null;
        }

        $row = $this->store->db->table('attempts a')
            ->select('q.share_token')
            ->join('quizzes q', 'q.id = a.quiz_id')
            ->where('a.public_id', $publicId)
            ->get()->getRowArray();

        return $row === null ? null : $this->cookieName((string) $row['share_token']);
    }

    /** Resolve the current cookie-owned attempt for a public quiz page. */
    public function current(string $shareToken, mixed $cookieValue): ?array
    {
        if (! QuizShareCode::isValid($shareToken)
            || ! is_string($cookieValue)
            || ! $this->validPublicId($cookieValue)) {
            return null;
        }

        $attempt = $this->store->db->table('attempts a')
            ->select('a.*')
            ->join('quizzes q', 'q.id = a.quiz_id')
            ->where('a.public_id', $cookieValue)
            ->where('q.share_token', $shareToken)
            ->get()->getRowArray();

        if ($attempt === null || $this->authorizationExpired($attempt)) {
            return null;
        }

        return $attempt;
    }

    // Enforce the post-deadline cookie grace period.
    public function authorizationExpired(array $attempt): bool
    {
        return PlayerStore::now() >= PlayerStore::due(
            (string) $attempt['expires_at'],
            self::GRACE_SECONDS,
        );
    }

    // Check the attempt public ID format.
    private function validPublicId(string $publicId): bool
    {
        return preg_match('/^[a-f0-9]{32}$/D', $publicId) === 1;
    }
}
