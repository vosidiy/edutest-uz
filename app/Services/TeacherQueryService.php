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

    /** @return array<string, mixed> */
    public function dashboard(int $userId): array
    {
        $base = fn () => $this->db->table('quizzes')->where('user_id', $userId)->where('deleted_at', null);
        $total = $base()->where('status !=', 'archived')->countAllResults();
        $published = $base()->where('status', 'published')->countAllResults();

        $assessmentSubmissions = $this->db->table('attempts a')
            ->join('quizzes q', 'q.id = a.quiz_id')
            ->where('q.user_id', $userId)
            ->whereIn('a.status', ['submitted', 'expired'])
            ->countAllResults();

        $practiceRow = $base()->selectSum('practice_starts', 'total')->get()->getRowArray();
        $recent = $base()->orderBy('updated_at', 'DESC')->limit(5)->get()->getResultArray();

        return [
            'metrics' => [
                'totalQuizzes'          => $total,
                'publishedQuizzes'      => $published,
                'assessmentSubmissions' => $assessmentSubmissions,
                'practiceStarts'        => (int) ($practiceRow['total'] ?? 0),
            ],
            'recent' => array_map(fn (array $quiz): array => $this->quizRow($quiz), $recent),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    public function library(int $userId, array $filters, string $view = 'active'): array
    {
        $query = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $mode = (string) ($filters['mode'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'updated_desc');
        if (! in_array($sort, ['updated_desc', 'created_desc', 'title_asc'], true)) {
            $sort = 'updated_desc';
        }
        $page = max(1, (int) ($filters['page'] ?? 1));

        $builder = $this->db->table('quizzes')->where('user_id', $userId);
        if ($view === 'trash') {
            $builder->where('deleted_at IS NOT NULL', null, false);
        } elseif ($view === 'archived') {
            $builder->where('deleted_at', null)->where('status', 'archived');
        } else {
            $builder->where('deleted_at', null)->where('status !=', 'archived');
        }

        if ($query !== '') {
            $builder->like('title', mb_substr($query, 0, 200));
        }
        if (in_array($status, ['draft', 'published', 'closed', 'archived'], true)
            && ($view !== 'active' || $status !== 'archived')) {
            $builder->where('status', $status);
        }
        if (in_array($mode, ['assessment', 'practice'], true)) {
            $builder->where('mode', $mode);
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();
        [$sortField, $sortDirection] = match ($sort) {
            'title_asc'   => ['title', 'ASC'],
            'created_desc'=> ['created_at', 'DESC'],
            default       => ['updated_at', 'DESC'],
        };
        $rows = $builder
            ->orderBy($sortField, $sortDirection)
            ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)
            ->get()
            ->getResultArray();

        return [
            'rows' => array_map(fn (array $quiz): array => $this->quizRow($quiz), $rows),
            'pagination' => [
                'page'      => $page,
                'pageSize'  => self::PAGE_SIZE,
                'total'     => $total,
                'pageCount' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
            'filters' => compact('query', 'status', 'mode', 'sort'),
            'view'    => $view,
        ];
    }

    /** @return array<string, mixed> */
    private function quizRow(array $quiz): array
    {
        $questionCount = $this->db->table('questions')->where('quiz_id', $quiz['id'])->countAllResults();
        $attemptCount = $this->db->table('attempts')->where('quiz_id', $quiz['id'])
            ->whereIn('status', ['submitted', 'expired'])->countAllResults();

        return [
            'publicId'      => (string) $quiz['public_id'],
            'title'         => trim((string) $quiz['title']) === '' ? 'Untitled quiz' : (string) $quiz['title'],
            'mode'          => (string) $quiz['mode'],
            'status'        => (string) $quiz['status'],
            'frozen'        => $quiz['frozen_at'] !== null,
            'questionCount' => $questionCount,
            'responseCount' => $quiz['mode'] === 'practice' ? (int) $quiz['practice_starts'] : $attemptCount,
            'updatedAt'     => $this->utcAtom($quiz['updated_at']),
            'editUrl'       => site_url('quizzes/' . $quiz['public_id'] . '/edit'),
        ];
    }

    private function utcAtom(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
