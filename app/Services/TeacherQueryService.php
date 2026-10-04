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

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function normalizeFilters(array $input): array
    {
        $string = static fn (string $key, string $default = ''): string => is_scalar($input[$key] ?? null) ? (string) $input[$key] : $default;
        $allowed = static fn (string $value, array $values, string $default): string => in_array($value, $values, true) ? $value : $default;
        return [
            'status' => $allowed($string('status'), ['draft', 'published', 'closed', 'archived', 'trash'], ''),
            'mode' => $allowed($string('mode'), ['assessment', 'practice'], ''),
            'sort' => $allowed($string('sort'), ['updated_desc', 'created_desc', 'title_asc', 'submissions_desc'], 'updated_desc'),
            'page' => max(1, (int) $string('page', '1')),
        ];
    }

    /** Preserve only recognized legacy filters, never arbitrary destinations. */
    public function legacyDashboardUrl(array $input, string $source): string
    {
        if ($source === 'archived') {
            $input['status'] = 'archived';
        } elseif ($source === 'trash') {
            $input['status'] = 'trash';
        } elseif ($source === 'results') {
            $lifecycle = is_string($input['lifecycle'] ?? null) ? $input['lifecycle'] : 'all';
            $input['status'] = match ($lifecycle) { 'archived' => 'archived', 'deleted' => 'trash', default => '' };
        }
        return site_url('dashboard') . '?' . http_build_query(array_filter($this->normalizeFilters($input), static fn ($value): bool => $value !== ''));
    }

    public function library(int $userId, array $input = [], string $timezone = 'UTC'): array
    {
        $filters = $this->normalizeFilters($input);
        // Aggregate children separately: joining questions to attempts would multiply counts.
        $stats = $this->db->table('attempts a')->select('a.quiz_id')
            ->select('COUNT(*) AS attempt_count', false)
            ->select("SUM(CASE WHEN a.status IN ('completed', 'abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->join('quizzes owner', 'owner.id = a.quiz_id')->where('owner.user_id', $userId)
            ->groupBy('a.quiz_id')->getCompiledSelect();
        $questions = $this->db->table('questions question')->select('question.quiz_id')->select('COUNT(*) AS question_count', false)
            ->join('quizzes owner', 'owner.id = question.quiz_id')->where('owner.user_id', $userId)
            ->groupBy('question.quiz_id')->getCompiledSelect();
        $builder = $this->db->table('quizzes q')
            ->join('(' . $stats . ') stats', 'stats.quiz_id = q.id', 'left', false)
            ->join('(' . $questions . ') qc', 'qc.quiz_id = q.id', 'left', false)
            ->where('q.user_id', $userId);
        if ($filters['status'] === 'trash') {
            $builder->where('q.deleted_at IS NOT NULL', null, false);
        } else {
            $builder->where('q.deleted_at', null);
            if ($filters['status'] !== '') $builder->where('q.status', $filters['status']);
        }
        if ($filters['mode'] !== '') $builder->where('q.mode', $filters['mode']);
        $total = (clone $builder)->countAllResults();
        $pageCount = max(1, (int) ceil($total / self::PAGE_SIZE));
        $filters['page'] = min($filters['page'], $pageCount);
        foreach (['q', 'stats', 'qc'] as $alias) $this->db->addTableAlias($alias);
        $builder->select('q.public_id, q.title, q.mode, q.status, q.deleted_at, q.current_paper_id, q.practice_starts, q.updated_at')
            ->select('qc.question_count, stats.attempt_count, stats.finalized_count');
        [$field, $direction] = match ($filters['sort']) {
            'created_desc' => ['q.created_at', 'DESC'], 'title_asc' => ['q.title', 'ASC'],
            'submissions_desc' => ['COALESCE(stats.finalized_count, 0)', 'DESC'],
            default => ['q.updated_at', 'DESC'],
        };
        $rows = $builder->orderBy($field, $direction, false)->orderBy('q.id', 'DESC')
            ->limit(self::PAGE_SIZE, ($filters['page'] - 1) * self::PAGE_SIZE)->get()->getResultArray();
        return [
            'rows' => array_map(fn (array $row): array => $this->quizRow($row, $timezone), $rows),
            'filters' => $filters,
            'pagination' => ['page' => $filters['page'], 'pageSize' => self::PAGE_SIZE, 'total' => $total, 'pageCount' => $pageCount],
        ];
    }

    private function quizRow(array $quiz, string $timezone): array
    {
        $canEdit = $quiz['deleted_at'] === null && $quiz['status'] !== 'archived';
        $resultsAvailable = $quiz['mode'] === 'assessment' || (int) ($quiz['attempt_count'] ?? 0) > 0;
        return [
            'publicId' => (string) $quiz['public_id'],
            'title' => trim((string) $quiz['title']) === '' ? lang('Results.untitledQuiz') : (string) $quiz['title'],
            'mode' => (string) $quiz['mode'], 'status' => (string) $quiz['status'], 'deleted' => $quiz['deleted_at'] !== null,
            'hasPublished' => $quiz['current_paper_id'] !== null, 'questionCount' => (int) ($quiz['question_count'] ?? 0),
            'assessmentSubmissions' => (int) ($quiz['finalized_count'] ?? 0),
            'practiceStarts' => (int) $quiz['practice_starts'], 'updatedAt' => $this->localDate($quiz['updated_at'], $timezone),
            'editUrl' => $canEdit ? site_url('quizzes/' . $quiz['public_id'] . '/edit') : null,
            'resultsUrl' => $resultsAvailable ? site_url('results/quizzes/' . $quiz['public_id']) : null,
        ];
    }

    private function localDate(mixed $value, string $timezone): ?string
    {
        if ($value === null || $value === '') return null;
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone))->format('d M Y, H:i');
    }
}
