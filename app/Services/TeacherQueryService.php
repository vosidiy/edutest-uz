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

    public function dashboard(int $userId, array $input = [], string $timezone = 'UTC'): array
    {
        $base = fn () => $this->db->table('quizzes')->where('user_id', $userId)->where('deleted_at', null);
        $total = $base()->where('status !=', 'archived')->countAllResults();
        $published = $base()->where('status', 'published')->countAllResults();
        $practice = $base()->selectSum('practice_starts', 'total')->get()->getRowArray();
        $aggregate = $this->db->table('attempts a')
            ->select("SUM(CASE WHEN a.status IN ('submitted', 'expired') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN a.status IN ('submitted', 'expired') THEN a.percent END) AS average_percent", false)
            ->join('quizzes q', 'q.id = a.quiz_id')->where('q.user_id', $userId)->get()->getRowArray();
        $filters = $this->normalizeFilters($input);
        return [
            'metrics' => [
                'totalQuizzes' => $total, 'publishedQuizzes' => $published,
                'assessmentSubmissions' => (int) ($aggregate['finalized_count'] ?? 0),
                'inProgressAttempts' => (int) ($aggregate['in_progress_count'] ?? 0),
                'averagePercent' => $this->decimal($aggregate['average_percent'] ?? null),
                'practiceStarts' => (int) ($practice['total'] ?? 0),
            ],
            'library' => $this->library($userId, $input, $filters['view'], $timezone),
        ];
    }

    public function normalizeFilters(array $input): array
    {
        $string = static fn (string $key, string $default = ''): string => is_scalar($input[$key] ?? null) ? (string) $input[$key] : $default;
        $allowed = static fn (string $value, array $values, string $default): string => in_array($value, $values, true) ? $value : $default;
        return [
            'q' => mb_substr(trim($string('q')), 0, 200),
            'view' => $allowed($string('view'), ['all', 'active', 'archived', 'trash'], 'active'),
            'status' => $allowed($string('status'), ['draft', 'published', 'closed', 'archived'], ''),
            'mode' => $allowed($string('mode'), ['assessment', 'practice'], ''),
            'reports' => $string('reports') === '1' ? '1' : '',
            'sort' => $allowed($string('sort'), ['updated_desc', 'created_desc', 'title_asc', 'submissions_desc', 'score_desc'], 'updated_desc'),
            'page' => max(1, (int) $string('page', '1')),
        ];
    }

    /** Preserve only recognized legacy filters, never arbitrary destinations. */
    public function legacyDashboardUrl(array $input, string $source): string
    {
        if ($source === 'results') {
            $lifecycle = is_string($input['lifecycle'] ?? null) ? $input['lifecycle'] : 'all';
            $input['view'] = match ($lifecycle) { 'active' => 'active', 'archived' => 'archived', 'deleted' => 'trash', default => 'all' };
            $input['reports'] = '1';
        } else {
            $input['view'] = $source;
        }
        return site_url('dashboard') . '?' . http_build_query(array_filter($this->normalizeFilters($input), static fn ($value): bool => $value !== ''));
    }

    public function library(int $userId, array $input, string $view = 'active', string $timezone = 'UTC'): array
    {
        $filters = $this->normalizeFilters(array_replace($input, ['view' => $view]));
        // Aggregate children separately: joining questions to attempts would multiply counts.
        $stats = $this->db->table('attempts a')->select('a.quiz_id')
            ->select('COUNT(*) AS attempt_count', false)
            ->select("SUM(CASE WHEN a.status IN ('submitted', 'expired') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN a.status IN ('submitted', 'expired') THEN a.percent END) AS average_percent", false)
            ->select("MAX(CASE WHEN a.status IN ('submitted', 'expired') THEN a.submitted_at END) AS latest_submission", false)
            ->join('quizzes owner', 'owner.id = a.quiz_id')->where('owner.user_id', $userId)
            ->groupBy('a.quiz_id')->getCompiledSelect();
        $questions = $this->db->table('questions question')->select('question.quiz_id')->select('COUNT(*) AS question_count', false)
            ->join('quizzes owner', 'owner.id = question.quiz_id')->where('owner.user_id', $userId)
            ->groupBy('question.quiz_id')->getCompiledSelect();
        $builder = $this->db->table('quizzes q')
            ->join('(' . $stats . ') stats', 'stats.quiz_id = q.id', 'left', false)
            ->join('(' . $questions . ') qc', 'qc.quiz_id = q.id', 'left', false)
            ->where('q.user_id', $userId);
        if ($filters['view'] === 'trash') {
            $builder->where('q.deleted_at IS NOT NULL', null, false);
        } elseif ($filters['view'] !== 'all') {
            $builder->where('q.deleted_at', null);
            $builder->where($filters['view'] === 'archived' ? 'q.status' : 'q.status !=', 'archived');
        }
        if ($filters['q'] !== '') $builder->like('q.title', $filters['q']);
        if ($filters['status'] !== '') $builder->where('q.status', $filters['status']);
        if ($filters['mode'] !== '') $builder->where('q.mode', $filters['mode']);
        if ($filters['reports'] === '1') $builder->groupStart()->where('q.mode', 'assessment')->orWhere('stats.attempt_count >', 0)->groupEnd();
        $total = (clone $builder)->countAllResults();
        $pageCount = max(1, (int) ceil($total / self::PAGE_SIZE));
        $filters['page'] = min($filters['page'], $pageCount);
        foreach (['q', 'stats', 'qc'] as $alias) $this->db->addTableAlias($alias);
        $builder->select('q.public_id, q.title, q.mode, q.status, q.deleted_at, q.first_started_at, q.practice_starts, q.updated_at')
            ->select('qc.question_count, stats.attempt_count, stats.finalized_count, stats.in_progress_count, stats.average_percent, stats.latest_submission');
        [$field, $direction] = match ($filters['sort']) {
            'created_desc' => ['q.created_at', 'DESC'], 'title_asc' => ['q.title', 'ASC'],
            'submissions_desc' => ['COALESCE(stats.finalized_count, 0)', 'DESC'], 'score_desc' => ['stats.average_percent', 'DESC'],
            default => ['q.updated_at', 'DESC'],
        };
        $rows = $builder->orderBy($field, $direction, false)->orderBy('q.id', 'DESC')
            ->limit(self::PAGE_SIZE, ($filters['page'] - 1) * self::PAGE_SIZE)->get()->getResultArray();
        return [
            'rows' => array_map(fn (array $row): array => $this->quizRow($row, $timezone), $rows),
            'filters' => $filters, 'view' => $filters['view'],
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
            'hasStarted' => $quiz['first_started_at'] !== null, 'questionCount' => (int) ($quiz['question_count'] ?? 0),
            'assessmentSubmissions' => (int) ($quiz['finalized_count'] ?? 0), 'inProgressAttempts' => (int) ($quiz['in_progress_count'] ?? 0),
            'averagePercent' => $this->decimal($quiz['average_percent']), 'latestSubmission' => $this->localDate($quiz['latest_submission'], $timezone),
            'practiceStarts' => (int) $quiz['practice_starts'], 'updatedAt' => $this->localDate($quiz['updated_at'], $timezone),
            'editUrl' => $canEdit ? site_url('quizzes/' . $quiz['public_id'] . '/edit') : null,
            'resultsUrl' => $resultsAvailable ? site_url('results/quizzes/' . $quiz['public_id']) : null,
        ];
    }

    private function decimal(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }

    private function localDate(mixed $value, string $timezone): ?string
    {
        if ($value === null || $value === '') return null;
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone))->format('d M Y, H:i');
    }
}
