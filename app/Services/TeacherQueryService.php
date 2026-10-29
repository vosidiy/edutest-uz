<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;

final class TeacherQueryService
{
    private const PAGE_SIZE = 20;
    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null, private readonly ?MediaService $media = null)
    {
        $this->db = $db ?? Database::connect();
    }

    private function page(array $input): int
    {
        return max(1, (int) (is_scalar($input['page'] ?? null) ? $input['page'] : 1));
    }

    public function library(int $userId, array $input = [], string $timezone = 'UTC'): array
    {
        $page = $this->page($input);
        $stats = $this->db->table('attempts')->select('quiz_id')
            ->select('COUNT(*) AS attempt_count', false)
            ->select("SUM(CASE WHEN status IN ('completed','abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->groupBy('quiz_id')->getCompiledSelect();
        $questions = $this->db->table('questions')->select('quiz_id')->select('COUNT(*) AS question_count', false)
            ->groupBy('quiz_id')->getCompiledSelect();
        $builder = $this->db->table('quizzes q')
            ->join('(' . $stats . ') stats', 'stats.quiz_id = q.id', 'left', false)
            ->join('(' . $questions . ') qc', 'qc.quiz_id = q.id', 'left', false)
            ->where('q.user_id', $userId)
            ->where('q.deleted_at', null);

        $total = (clone $builder)->countAllResults();
        $pageCount = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pageCount);
        foreach (['q', 'stats', 'qc'] as $alias) $this->db->addTableAlias($alias);
        $builder->select('q.id, q.public_id, q.title, q.status, q.cover_src, q.updated_at')
            ->select('qc.question_count, stats.attempt_count, stats.finalized_count');
        $rows = $builder->orderBy('q.updated_at', 'DESC')->orderBy('q.id', 'DESC')
            ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)->get()->getResultArray();

        return [
            'rows' => array_map(fn (array $row): array => [
                'publicId'             => (string) $row['public_id'],
                'title'                => trim((string) $row['title']) === '' ? lang('Results.untitledQuiz') : (string) $row['title'],
                'status'               => (string) $row['status'],
                'questionCount'        => (int) ($row['question_count'] ?? 0),
                'cover'                => $this->media?->descriptor($row['cover_src'] === null ? null : 'image', $row['cover_src'], 'cover', (int) $row['id']),
                'assessmentSubmissions'=> (int) ($row['finalized_count'] ?? 0),
                'updatedAt'            => $this->localDate($row['updated_at'], $timezone),
                'editUrl'              => site_url('quizzes/' . $row['public_id'] . '/edit'),
                'resultsUrl'           => (int) ($row['attempt_count'] ?? 0) > 0 ? site_url('results/quizzes/' . $row['public_id']) : null,
            ], $rows),
            'filters' => ['page' => $page],
            'pagination' => ['page' => $page, 'pageSize' => self::PAGE_SIZE, 'total' => $total, 'pageCount' => $pageCount],
        ];
    }

    private function localDate(mixed $value, string $timezone): ?string
    {
        if ($value === null || $value === '') return null;
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($timezone))->format('d M Y, H:i');
    }
}
