<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthoringException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;

final class PublicProfileService
{
    private const PAGE_SIZE = 20;
    private const USERNAME_PATTERN = '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D';

    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null, private readonly ?MediaService $media = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** @return array{username: string, valid: bool, available: bool} */
    public function availability(int $userId, mixed $value): array
    {
        $username = $this->normalizeUsername($value);
        $valid = $this->validUsername($username);

        return [
            'username'  => $username,
            'valid'     => $valid,
            'available' => $valid && ! $this->usernameBelongsToAnotherUser($username, $userId),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array{username: string, bio: string, publicPage: bool, url: string}
     */
    public function update(int $userId, array $input): array
    {
        $username = $this->normalizeUsername($input['username'] ?? null);
        $bio = $this->normalizeBio($input['bio'] ?? null);
        $publicPage = $this->boolean($input['publicPage'] ?? false);
        $fields = [];

        if (! $this->validUsername($username)) {
            $fields['username'] = 'Use 3–30 lowercase letters, numbers, or single hyphens.';
        } elseif ($this->usernameBelongsToAnotherUser($username, $userId)) {
            $fields['username'] = 'That username is already in use.';
        }
        if (mb_strlen($bio) > 1000) {
            $fields['bio'] = 'Bio must be 1,000 characters or fewer.';
        }
        if ($fields !== []) {
            throw new AuthoringException('validation_failed', 'Check the public page details and try again.', 422, $fields);
        }

        try {
            $updated = $this->db->table('users')
                ->where('id', $userId)
                ->where('active', 1)
                ->where('deleted_at', null)
                ->update([
                    'username'    => $username,
                    'bio'         => $bio,
                    'public_page' => $publicPage ? 1 : 0,
                    'updated_at'  => gmdate('Y-m-d H:i:s'),
                ]);
        } catch (DatabaseException) {
            throw new AuthoringException('username_taken', 'That username is already in use.', 409, [
                'username' => 'That username is already in use.',
            ]);
        }

        if (! $updated) {
            throw new AuthoringException('profile_update_failed', 'Your public page could not be updated.', 500);
        }

        return [
            'username'   => $username,
            'bio'        => $bio,
            'publicPage' => $publicPage,
            'url'        => site_url('user/' . $username),
        ];
    }

    /** @return array<string, mixed>|null */
    public function page(string $requestedUsername, mixed $requestedPage = 1): ?array
    {
        $username = $this->normalizeUsername($requestedUsername);
        if ($username !== $requestedUsername || ! $this->validUsername($username)) {
            return null;
        }

        $teacher = $this->db->table('users')
            ->select('id, username, display_name, bio')
            ->where('username', $username)
            ->where('public_page', 1)
            ->where('active', 1)
            ->where('deleted_at', null)
            ->get()->getRowArray();
        if ($teacher === null) {
            return null;
        }

        $page = max(1, (int) (is_scalar($requestedPage) ? $requestedPage : 1));
        $builder = $this->db->table('quizzes q')
            ->where('q.user_id', (int) $teacher['id'])
            ->where('q.status', 'published')
            ->where('q.deleted_at', null);
        $total = (clone $builder)->countAllResults();
        $pageCount = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pageCount);

        $questions = $this->db->table('questions')
            ->select('quiz_id')
            ->select('COUNT(*) AS question_count', false)
            ->groupBy('quiz_id')
            ->getCompiledSelect();
        $this->db->addTableAlias('q');
        $this->db->addTableAlias('qc');
        $rows = $builder
            ->select('q.id, q.share_token, q.title, q.description, q.cover_src, q.time_limit_sec, q.draw_count, q.updated_at')
            ->select('qc.question_count')
            ->join('(' . $questions . ') qc', 'qc.quiz_id = q.id', 'left', false)
            ->orderBy('q.updated_at', 'DESC')
            ->orderBy('q.id', 'DESC')
            ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)
            ->get()->getResultArray();

        return [
            'teacher' => [
                'username'    => (string) $teacher['username'],
                'displayName' => (string) $teacher['display_name'],
                'bio'         => (string) $teacher['bio'],
            ],
            'quizzes' => array_map(function (array $row): array {
                $pool = (int) ($row['question_count'] ?? 0);

                return [
                    'title'         => trim((string) $row['title']) === '' ? lang('Results.untitledQuiz') : (string) $row['title'],
                    'description'   => (string) $row['description'],
                    'questionCount' => $row['draw_count'] === null ? $pool : min($pool, (int) $row['draw_count']),
                    'timeLimit'     => $this->minutes((int) $row['time_limit_sec']),
                    'url'           => site_url('q/' . $row['share_token']),
                    'cover'         => ($this->media ?? service('media'))->descriptor(
                        $row['cover_src'] === null ? null : 'image',
                        $row['cover_src'],
                        'cover',
                        (int) $row['id'],
                    ),
                ];
            }, $rows),
            'pagination' => [
                'page'      => $page,
                'pageSize'  => self::PAGE_SIZE,
                'total'     => $total,
                'pageCount' => $pageCount,
            ],
        ];
    }

    public function normalizeUsername(mixed $value): string
    {
        return is_string($value) ? strtolower(trim($value)) : '';
    }

    private function validUsername(string $username): bool
    {
        $length = strlen($username);

        return $length >= 3 && $length <= 30 && preg_match(self::USERNAME_PATTERN, $username) === 1;
    }

    private function usernameBelongsToAnotherUser(string $username, int $userId): bool
    {
        return $this->db->table('users')
            ->where('username', $username)
            ->where('id !=', $userId)
            ->countAllResults() > 0;
    }

    private function normalizeBio(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $trimmed = preg_replace('/\A\s+|\s+\z/u', '', $value);

        return $trimmed ?? trim($value);
    }

    private function boolean(mixed $value): bool
    {
        return $value === true || $value === 1 || in_array($value, ['1', 'true', 'on'], true);
    }

    private function minutes(int $seconds): string
    {
        return rtrim(rtrim(number_format($seconds / 60, 1, '.', ''), '0'), '.');
    }
}
