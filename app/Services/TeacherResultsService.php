<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReportingException;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class TeacherResultsService
{
    private const OVERVIEW_PAGE_SIZE = 20;
    private const ATTEMPT_PAGE_SIZE = 25;
    private const EXPORT_BATCH_SIZE = 500;

    private readonly BaseConnection $db;

    public function __construct(
        ?BaseConnection $db = null,
        private readonly ?MediaService $media = null,
        private readonly ?QuizPaperService $papers = null,
    ) {
        $this->db = $db ?? Database::connect();
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function overview(int $userId, array $input, string $timezone): array
    {
        $filters = $this->normalizeOverviewFilters($input);
        $builder = $this->db->table('quizzes q')
            ->where('q.user_id', $userId);
        $this->applyAssessmentHistoryScope($builder);
        $this->applyOverviewFilters($builder, $filters);

        $total = (clone $builder)->countAllResults();
        $pageCount = max(1, (int) ceil($total / self::OVERVIEW_PAGE_SIZE));
        $filters['page'] = min($filters['page'], $pageCount);

        $this->restoreAliases('q');
        $rowsBuilder = $builder
            ->select('q.id, q.public_id, q.title, q.mode, q.status, q.deleted_at, q.updated_at')
            ->select("SUM(CASE WHEN a.status IN ('completed', 'abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN a.status IN ('completed', 'abandoned') THEN a.percent ELSE NULL END) AS average_percent", false)
            ->select("MAX(CASE WHEN a.status IN ('completed', 'abandoned') THEN a.finished_at ELSE NULL END) AS latest_submission", false)
            ->join('attempts a', 'a.quiz_id = q.id', 'left')
            ->groupBy('q.id, q.public_id, q.title, q.mode, q.status, q.deleted_at, q.updated_at');

        match ($filters['sort']) {
            'title_asc'        => $rowsBuilder->orderBy('q.title', 'ASC')->orderBy('q.id', 'ASC'),
            'submissions_desc' => $rowsBuilder->orderBy('finalized_count', 'DESC')->orderBy('q.updated_at', 'DESC'),
            'score_desc'       => $rowsBuilder->orderBy('average_percent', 'DESC')->orderBy('q.updated_at', 'DESC'),
            default            => $rowsBuilder->orderBy('q.updated_at', 'DESC')->orderBy('q.id', 'DESC'),
        };

        $this->restoreAliases('q', 'a');
        $rows = $rowsBuilder
            ->limit(self::OVERVIEW_PAGE_SIZE, ($filters['page'] - 1) * self::OVERVIEW_PAGE_SIZE)
            ->get()
            ->getResultArray();

        $aggregate = $this->db->table('attempts a')
            ->select("SUM(CASE WHEN a.status IN ('completed', 'abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN a.status IN ('completed', 'abandoned') THEN a.percent ELSE NULL END) AS average_percent", false)
            ->join('quizzes q', 'q.id = a.quiz_id')
            ->where('q.user_id', $userId)
            ->get()
            ->getRowArray() ?? [];

        return [
            'metrics' => [
                'finalizedAttempts' => (int) ($aggregate['finalized_count'] ?? 0),
                'inProgressAttempts' => (int) ($aggregate['in_progress_count'] ?? 0),
                'averagePercent'    => $this->decimalOrNull($aggregate['average_percent'] ?? null),
            ],
            'rows' => array_map(fn (array $row): array => [
                'publicId'       => (string) $row['public_id'],
                'title'          => trim((string) $row['title']) !== '' ? (string) $row['title'] : lang('Results.untitledQuiz'),
                'currentMode'    => (string) $row['mode'],
                'status'         => $row['deleted_at'] !== null ? 'deleted' : (string) $row['status'],
                'finalizedCount' => (int) $row['finalized_count'],
                'inProgressCount'=> (int) $row['in_progress_count'],
                'averagePercent'=> $this->decimalOrNull($row['average_percent']),
                'latestSubmission' => $this->localDate($row['latest_submission'], $timezone),
                'updatedAt'      => $this->localDate($row['updated_at'], $timezone),
                'url'            => site_url('results/quizzes/' . $row['public_id']),
            ], $rows),
            'filters' => $filters,
            'pagination' => [
                'page'      => $filters['page'],
                'pageSize'  => self::OVERVIEW_PAGE_SIZE,
                'total'     => $total,
                'pageCount' => $pageCount,
            ],
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function quiz(int $userId, string $quizPublicId, array $input, string $timezone): array
    {
        $quiz = $this->ownedQuiz($userId, $quizPublicId);
        $this->resolveOverdue((int) $quiz['id']);
        $filters = $this->normalizeAttemptFilters($input, true, $timezone);
        $attemptBuilder = $this->attemptBuilder((int) $quiz['id'], $filters);
        $total = (clone $attemptBuilder)->countAllResults();
        $pageCount = max(1, (int) ceil($total / self::ATTEMPT_PAGE_SIZE));
        $filters['page'] = min($filters['page'], $pageCount);

        $this->restoreAliases('a', 'ev', 'p');
        $rows = $this->selectAttemptRows($attemptBuilder, $filters['sort'])
            ->limit(self::ATTEMPT_PAGE_SIZE, ($filters['page'] - 1) * self::ATTEMPT_PAGE_SIZE)
            ->get()
            ->getResultArray();

        $metrics = $this->quizMetrics((int) $quiz['id']);
        $query = $this->attemptFilterQuery($filters);

        return [
            'quiz' => [
                'publicId' => (string) $quiz['public_id'],
                'title'    => trim((string) $quiz['title']) !== '' ? (string) $quiz['title'] : lang('Results.untitledQuiz'),
                'builderUrl' => $quiz['deleted_at'] === null && $quiz['status'] !== 'archived' ? site_url('quizzes/' . $quiz['public_id'] . '/edit') : null,
                'restoreUrl' => site_url('dashboard') . '?' . http_build_query(['view' => $quiz['deleted_at'] !== null ? 'trash' : 'archived', 'q' => $quiz['title']]),
                'currentMode' => (string) $quiz['mode'],
                'status'   => $quiz['deleted_at'] !== null ? 'deleted' : (string) $quiz['status'],
            ],
            'metrics'      => $metrics,
            'attempts' => array_map(fn (array $row): array => $this->attemptRow($row, $timezone), $rows),
            'filters' => $filters,
            'pagination' => [
                'page'      => $filters['page'],
                'pageSize'  => self::ATTEMPT_PAGE_SIZE,
                'total'     => $total,
                'pageCount' => $pageCount,
            ],
            'exportUrl' => site_url('results/quizzes/' . $quiz['public_id'] . '/export.csv')
                . ($query !== '' ? '?' . $query : ''),
        ];
    }

    /** @return array<string, mixed> */
    public function attempt(int $userId, string $attemptPublicId, string $timezone): array
    {
        if (! $this->validPublicId($attemptPublicId)) {
            throw new ReportingException('attempt_not_found', 'Attempt not found.');
        }

        $attempt = $this->db->table('attempts a')
            ->select('a.id, a.public_id, a.quiz_id, a.paper_id, a.name, a.email, a.phone, a.ip, a.agent, a.status, a.started_at, a.last_activity_at, a.client_activity_at, a.late_sync, a.expires_at, a.deadline_reason, a.finished_at, a.ended_reason, a.score, a.max_score, a.percent')
            ->select('q.public_id AS quiz_public_id, q.title AS quiz_title, q.mode AS quiz_mode, q.status AS quiz_status, q.deleted_at AS quiz_deleted_at')
            ->select('p.public_id AS paper_public_id, p.revision AS paper_revision, p.definition AS paper_definition')
            ->join('quizzes q', 'q.id = a.quiz_id')
            ->join('quiz_papers p', 'p.id = a.paper_id AND p.quiz_id = a.quiz_id')
            ->where('a.public_id', $attemptPublicId)
            ->where('q.user_id', $userId)
            ->get()
            ->getRowArray();

        if ($attempt === null) {
            throw new ReportingException('attempt_not_found', 'Attempt not found.');
        }
        $this->resolveOverdue((int) $attempt['quiz_id']);
        $attempt = $this->db->table('attempts')->where('id', $attempt['id'])->get()->getRowArray() + $attempt;

        $paper = [
            'id' => $attempt['paper_id'], 'quiz_id' => $attempt['quiz_id'],
            'public_id' => $attempt['paper_public_id'], 'revision' => $attempt['paper_revision'],
            'definition' => $attempt['paper_definition'],
        ];
        try {
            $definition = $this->papers()->definition($paper);
        } catch (Throwable) {
            throw new ReportingException('attempt_not_found', 'Attempt not found.');
        }
        $snapshotById = [];
        foreach ($definition['questions'] as $question) {
            $snapshotById[(string) $question['id']] = $question;
        }

        $items = $this->db->table('attempt_answers')->where('attempt_id', $attempt['id'])->orderBy('pos')->get()->getResultArray();

        $events = $this->db->table('cheat_events')
            ->select('type, happened_at, received_at, duration_ms, data')
            ->where('attempt_id', (int) $attempt['id'])
            ->orderBy('received_at', 'ASC')->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $questionReviews = [];
        $activeAssigned = false;
        foreach ($items as $index => $item) {
            $snapshot = $snapshotById[(string) $item['question_id']] ?? null;
            if (is_array($snapshot)) {
                $status = $item['status'] === 'not_reached'
                    ? (! $activeAssigned && $attempt['status'] === 'in_progress' ? 'active' : 'pending')
                    : 'locked';
                if ($status === 'active') $activeAssigned = true;
                $questionReviews[] = $this->questionReview($item, $snapshot, $paper, $status, $timezone);
            }
        }

        $settings = $this->papers()->settings($paper);
        $duration = $this->durationSeconds($attempt['started_at'], $attempt['finished_at'] ?? $attempt['last_activity_at']);
        $paperQuiz = $definition['quiz'];

        return [
            'attempt' => [
                'publicId' => (string) $attempt['public_id'], 'name' => (string) $attempt['name'],
                'email' => $this->nullableString($attempt['email']), 'phone' => $this->nullableString($attempt['phone']),
                'ip' => $this->nullableString($attempt['ip']), 'agent' => $this->nullableString($attempt['agent']),
                'status' => (string) $attempt['status'],
                'finishReason' => $this->nullableString($attempt['ended_reason']),
                'lateSync' => (bool) $attempt['late_sync'],
                'score' => $this->decimalOrNull($attempt['score']), 'maxScore' => $this->decimal((string) $attempt['max_score']),
                'percent' => $this->decimalOrNull($attempt['percent']),
                'startedAt' => $this->localDate($attempt['started_at'], $timezone),
                'submittedAt' => $this->localDate($attempt['finished_at'], $timezone),
                'updatedAt' => $this->localDate($attempt['last_activity_at'], $timezone),
                'expiresAt' => $this->localDate($attempt['expires_at'], $timezone),
                'deadlineReason' => (string) $attempt['deadline_reason'],
                'durationSec' => $duration, 'duration' => $this->formatDuration($duration),
                'paperRevision' => (int) $attempt['paper_revision'],
            ],
            'quiz' => [
                'publicId' => (string) $attempt['quiz_public_id'],
                'title' => trim((string) $attempt['quiz_title']) !== '' ? (string) $attempt['quiz_title'] : lang('Results.untitledQuiz'),
                'builderUrl' => $attempt['quiz_deleted_at'] === null && $attempt['quiz_status'] !== 'archived' ? site_url('quizzes/' . $attempt['quiz_public_id'] . '/edit') : null,
                'restoreUrl' => site_url('dashboard') . '?' . http_build_query(['view' => $attempt['quiz_deleted_at'] !== null ? 'trash' : 'archived', 'q' => $attempt['quiz_title']]),
                'currentMode' => (string) $attempt['quiz_mode'],
                'status' => $attempt['quiz_deleted_at'] !== null ? 'deleted' : (string) $attempt['quiz_status'],
                'url' => site_url('results/quizzes/' . $attempt['quiz_public_id']),
            ],
            'paper' => [
                'revision' => (int) $attempt['paper_revision'],
                'mode' => (string) ($paperQuiz['mode'] ?? 'assessment'),
                'title' => trim((string) ($paperQuiz['title'] ?? '')) !== '' ? (string) $paperQuiz['title'] : lang('Results.untitledQuiz'),
                'description' => (string) ($paperQuiz['description'] ?? ''),
                'instructions' => (string) ($paperQuiz['instructions'] ?? ''),
            ],
            'policies' => $this->policySummary($settings, $definition['quiz'], $timezone),
            'questions' => $questionReviews,
            'events' => array_map(fn (array $event): array => [
                'type' => (string) $event['type'],
                'happenedAt' => $this->localDate($event['happened_at'], $timezone),
                'receivedAt' => $this->localDate($event['received_at'], $timezone),
                'durationMs' => $event['duration_ms'] === null ? null : (int) $event['duration_ms'],
                'metadata' => $this->sanitizeMetadata($this->jsonArray($event['data'])),
            ], $events),
            'timezone' => $timezone,
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array{quiz: array<string, mixed>, filters: array<string, mixed>, rows: iterable<array<string, mixed>>}
     */
    public function export(int $userId, string $quizPublicId, array $input, string $timezone): array
    {
        $quiz = $this->ownedQuiz($userId, $quizPublicId);
        $this->resolveOverdue((int) $quiz['id']);
        $filters = $this->normalizeAttemptFilters($input, false, $timezone);

        return [
            'quiz' => [
                'publicId' => (string) $quiz['public_id'],
                'title'    => trim((string) $quiz['title']) !== '' ? (string) $quiz['title'] : lang('Results.untitledQuiz'),
            ],
            'filters' => $filters,
            'rows'    => $this->exportRows((int) $quiz['id'], $filters, $timezone),
        ];
    }

    public function protectCsvCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

    /** @param array<string, mixed> $filters
     *  @return iterable<array<string, mixed>>
     */
    private function exportRows(int $quizId, array $filters, string $timezone): iterable
    {
        $offset = 0;
        do {
            $builder = $this->selectAttemptRows($this->attemptBuilder($quizId, $filters), $filters['sort']);
            $rows = $builder->limit(self::EXPORT_BATCH_SIZE, $offset)->get()->getResultArray();

            foreach ($rows as $row) {
                $duration = $this->durationSeconds($row['started_at'], $row['finished_at'] ?? $row['last_activity_at']);
                yield [
                    'attemptPublicId' => (string) $row['public_id'],
                    'name'            => (string) $row['name'],
                    'email'           => $this->nullableString($row['email']) ?? '',
                    'phone'           => $this->nullableString($row['phone']) ?? '',
                    'status'          => (string) $row['status'],
                    'finishReason'    => $this->nullableString($row['ended_reason']) ?? '',
                    'score'           => $this->decimalOrNull($row['score']) ?? '',
                    'maxScore'        => $this->decimal((string) $row['max_score']),
                    'percentage'      => $this->decimalOrNull($row['percent']) ?? '',
                    'startedAt'       => $this->localIso($row['started_at'], $timezone) ?? '',
                    'submittedAt'     => $this->localIso($row['finished_at'], $timezone) ?? '',
                    'durationSeconds' => $duration === null ? '' : (string) $duration,
                    'integrityEvents' => (string) (int) $row['integrity_count'],
                    'lateSync' => (bool) $row['late_sync'] ? 'yes' : 'no',
                ];
            }

            $offset += count($rows);
        } while (count($rows) === self::EXPORT_BATCH_SIZE);
    }

    /** @return array<string, mixed> */
    private function quizMetrics(int $quizId): array
    {
        $aggregate = $this->db->table('attempts')
            ->select("SUM(CASE WHEN status IN ('completed', 'abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN status IN ('completed', 'abandoned') THEN percent ELSE NULL END) AS average_percent", false)
            ->where('quiz_id', $quizId)
            ->get()->getRowArray() ?? [];

        return [
            'finalizedAttempts' => (int) ($aggregate['finalized_count'] ?? 0),
            'inProgressAttempts' => (int) ($aggregate['in_progress_count'] ?? 0),
            'averagePercent' => $this->decimalOrNull($aggregate['average_percent'] ?? null),
        ];
    }

    /** @param array<string, mixed> $item
     *  @return array<string, mixed>
     */
    private function questionReview(array $item, array $question, array $paper, string $status, string $timezone): array
    {
        $options = is_array($question['options'] ?? null) ? $question['options'] : [];
        $choiceOrder = array_map('strval', $this->jsonArray($item['presented_option_codes']));
        $selectedCodes = array_map('strval', $this->jsonArray($item['selected_option_codes']));
        $byCode = [];
        foreach ($options as $option) {
            $byCode[(string) $option['code']] = $option;
        }
        $ordered = [];
        foreach ($choiceOrder as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
                unset($byCode[$code]);
            }
        }
        foreach ($byCode as $option) {
            $ordered[] = $option;
        }

        $renderedOptions = [];
        $correctAnswers = [];
        $selectedAnswers = [];
        $allOptionCodes = [];
        foreach ($ordered as $index => $option) {
            $code = (string) $option['code'];
            $allOptionCodes[$code] = true;
            $selected = in_array($code, $selectedCodes, true);
            $correct = (bool) ($option['isCorrect'] ?? false);
            $content = (string) ($option['content'] ?? '');
            $label = $this->optionLabel($index);
            $display = $label . ') ' . $content;
            if ($selected) {
                $selectedAnswers[] = $display;
            }
            if ($correct) {
                $correctAnswers[] = $display;
            }
            $renderedOptions[] = [
                'label' => $label, 'content' => $content, 'selected' => $selected, 'correct' => $correct,
                'media' => $this->paperMedia($paper, $option['media'] ?? null),
            ];
        }
        foreach ($selectedCodes as $code) {
            if (! isset($allOptionCodes[$code])) {
                $selectedAnswers[] = lang('Results.unavailableChoice');
            }
        }

        $accepted = array_values(array_filter(array_map(static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', $question['acceptedAnswers'] ?? []), static fn (string $value): bool => $value !== ''));
        $isText = ($question['type'] ?? null) === 'short_text';

        return [
            'position' => (int) $item['pos'],
            'type' => (string) $question['type'],
            'content' => (string) $question['content'],
            'explanation' => $this->nullableString($question['explanation'] ?? null),
            'status' => $status,
            'result' => $item['status'] === 'not_reached' ? null : ($item['status'] === 'skipped' ? 'unanswered' : ((bool) $item['is_correct'] ? 'correct' : 'wrong')),
            'answer' => $isText ? $this->nullableString($item['text_answer']) : $selectedAnswers,
            'answeredAt' => $this->localDate($item['answered_at'], $timezone),
            'clientAnsweredAt' => $this->localDate($item['client_answered_at'] ?? null, $timezone),
            'correctAnswer' => $isText ? $accepted : $correctAnswers,
            'options' => $renderedOptions,
            'media' => $this->paperMedia($paper, $question['media'] ?? null),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function attemptBuilder(int $quizId, array $filters): BaseBuilder
    {
        $events = '(SELECT attempt_id, COUNT(*) AS integrity_count FROM '
            . $this->db->prefixTable('cheat_events') . ' GROUP BY attempt_id) ev';
        $builder = $this->db->table('attempts a')
            ->join($events, 'ev.attempt_id = a.id', 'left', false)
            ->join('quiz_papers p', 'p.id = a.paper_id AND p.quiz_id = a.quiz_id')
            ->where('a.quiz_id', $quizId);

        match ($filters['status']) {
            'finalized'   => $builder->whereIn('a.status', ['completed', 'abandoned']),
            'in_progress' => $builder->where('a.status', 'in_progress'),
            'completed'   => $builder->where('a.status', 'completed'),
            'abandoned'   => $builder->where('a.status', 'abandoned'),
            default       => null,
        };

        if ($filters['query'] !== '') {
            $builder->groupStart()
                ->like('a.name', $filters['query'])
                ->orLike('a.email', $filters['query'])
                ->orLike('a.phone', $filters['query'])
                ->groupEnd();
        }
        if ($filters['dateFromUtc'] !== null) {
            $builder->where('a.started_at >=', $filters['dateFromUtc']);
        }
        if ($filters['dateToUtc'] !== null) {
            $builder->where('a.started_at <', $filters['dateToUtc']);
        }
        if ($filters['minScore'] !== null) {
            $builder->where('a.percent >=', $filters['minScore']);
        }
        if ($filters['maxScore'] !== null) {
            $builder->where('a.percent <=', $filters['maxScore']);
        }
        if ($filters['integrity'] === 'flagged') {
            $builder->where('COALESCE(ev.integrity_count, 0) >', 0, false);
        } elseif ($filters['integrity'] === 'clear') {
            $builder->where('COALESCE(ev.integrity_count, 0) =', 0, false);
        }

        return $builder;
    }

    private function selectAttemptRows(BaseBuilder $builder, string $sort): BaseBuilder
    {
        $builder->select('a.public_id, a.name, a.email, a.phone, a.status, a.ended_reason, a.score, a.max_score, a.percent, a.started_at, a.finished_at, a.last_activity_at, a.late_sync')
            ->select('p.revision AS paper_revision')
            ->select('COALESCE(ev.integrity_count, 0) AS integrity_count', false);

        match ($sort) {
            'oldest'     => $builder->orderBy('a.started_at', 'ASC')->orderBy('a.id', 'ASC'),
            'score_desc' => $builder->orderBy('a.percent', 'DESC')->orderBy('a.started_at', 'DESC'),
            'score_asc'  => $builder->orderBy('a.percent', 'ASC')->orderBy('a.started_at', 'DESC'),
            'name_asc'   => $builder->orderBy('a.name', 'ASC')->orderBy('a.started_at', 'DESC'),
            default      => $builder->orderBy('a.started_at', 'DESC')->orderBy('a.id', 'DESC'),
        };

        return $builder;
    }

    /** @return array<string, mixed> */
    private function attemptRow(array $row, string $timezone): array
    {
        $duration = $this->durationSeconds($row['started_at'], $row['finished_at'] ?? $row['last_activity_at']);
        return [
            'publicId'      => (string) $row['public_id'],
            'name'          => (string) $row['name'],
            'email'         => $this->nullableString($row['email']),
            'phone'         => $this->nullableString($row['phone']),
            'status'        => (string) $row['status'],
            'finishReason'  => $this->nullableString($row['ended_reason']),
            'lateSync' => (bool) $row['late_sync'],
            'score'         => $this->decimalOrNull($row['score']),
            'maxScore'      => $this->decimal((string) $row['max_score']),
            'percent'       => $this->decimalOrNull($row['percent']),
            'startedAt'     => $this->localDate($row['started_at'], $timezone),
            'submittedAt'   => $this->localDate($row['finished_at'], $timezone),
            'durationSec'   => $duration,
            'duration'      => $this->formatDuration($duration),
            'integrityCount'=> (int) $row['integrity_count'],
            'paperRevision' => (int) $row['paper_revision'],
            'url'           => site_url('results/attempts/' . $row['public_id']),
        ];
    }

    /** @return array<string, mixed> */
    private function ownedQuiz(int $userId, string $publicId): array
    {
        if (! $this->validPublicId($publicId)) {
            throw new ReportingException('quiz_not_found', 'Quiz not found.');
        }

        $quiz = $this->db->table('quizzes')
            ->select('id, public_id, title, mode, status, deleted_at')
            ->where('public_id', $publicId)
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($quiz === null || ($quiz['mode'] !== 'assessment'
            && $this->db->table('attempts')->where('quiz_id', $quiz['id'])->countAllResults() === 0)) {
            throw new ReportingException('quiz_not_found', 'Quiz not found.');
        }

        return $quiz;
    }

    private function validPublicId(string $publicId): bool
    {
        return preg_match('/^[a-f0-9]{32}$/D', $publicId) === 1;
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function normalizeOverviewFilters(array $input): array
    {
        $lifecycle = (string) ($input['lifecycle'] ?? 'all');
        if (! in_array($lifecycle, ['all', 'active', 'archived', 'deleted'], true)) {
            $lifecycle = 'all';
        }
        $sort = (string) ($input['sort'] ?? 'updated_desc');
        if (! in_array($sort, ['updated_desc', 'title_asc', 'submissions_desc', 'score_desc'], true)) {
            $sort = 'updated_desc';
        }
        return [
            'query'     => mb_substr(trim((string) ($input['q'] ?? '')), 0, 200),
            'lifecycle' => $lifecycle,
            'sort'      => $sort,
            'page'      => max(1, (int) ($input['page'] ?? 1)),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applyOverviewFilters(BaseBuilder $builder, array $filters): void
    {
        if ($filters['query'] !== '') {
            $builder->like('q.title', $filters['query']);
        }
        if ($filters['lifecycle'] === 'active') {
            $builder->where('q.deleted_at', null)->where('q.status !=', 'archived');
        } elseif ($filters['lifecycle'] === 'archived') {
            $builder->where('q.deleted_at', null)->where('q.status', 'archived');
        } elseif ($filters['lifecycle'] === 'deleted') {
            $builder->where('q.deleted_at IS NOT NULL', null, false);
        }
    }

    private function applyAssessmentHistoryScope(BaseBuilder $builder): void
    {
        $attempts = $this->db->prefixTable('attempts');
        $builder->groupStart()
            ->where('q.mode', 'assessment')
            ->orWhere("EXISTS (SELECT 1 FROM {$attempts} history_attempt WHERE history_attempt.quiz_id = q.id)", null, false)
            ->groupEnd();
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function normalizeAttemptFilters(array $input, bool $withPage, string $timezone): array
    {
        $status = (string) ($input['status'] ?? 'finalized');
        if (! in_array($status, ['finalized', 'in_progress', 'completed', 'abandoned', 'all'], true)) {
            $status = 'finalized';
        }
        $integrity = (string) ($input['integrity'] ?? 'all');
        if (! in_array($integrity, ['all', 'flagged', 'clear'], true)) {
            $integrity = 'all';
        }
        $sort = (string) ($input['sort'] ?? 'newest');
        if (! in_array($sort, ['newest', 'oldest', 'score_desc', 'score_asc', 'name_asc'], true)) {
            $sort = 'newest';
        }
        $dateFrom = $this->validDate((string) ($input['dateFrom'] ?? ''));
        $dateTo = $this->validDate((string) ($input['dateTo'] ?? ''));
        $minScore = $this->scoreFilter($input['minScore'] ?? null);
        $maxScore = $this->scoreFilter($input['maxScore'] ?? null);
        if ($minScore !== null && $maxScore !== null && $minScore > $maxScore) {
            [$minScore, $maxScore] = [$maxScore, $minScore];
        }

        return [
            'query'       => mb_substr(trim((string) ($input['q'] ?? '')), 0, 200),
            'status'      => $status,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'dateFromUtc' => $this->dateBoundary($dateFrom, $timezone, false),
            'dateToUtc'   => $this->dateBoundary($dateTo, $timezone, true),
            'minScore'    => $minScore,
            'maxScore'    => $maxScore,
            'integrity'   => $integrity,
            'sort'        => $sort,
            'page'        => $withPage ? max(1, (int) ($input['page'] ?? 1)) : 1,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function attemptFilterQuery(array $filters): string
    {
        $query = array_filter([
            'q'         => $filters['query'],
            'status'    => $filters['status'] !== 'finalized' ? $filters['status'] : null,
            'dateFrom'  => $filters['dateFrom'],
            'dateTo'    => $filters['dateTo'],
            'minScore'  => $filters['minScore'],
            'maxScore'  => $filters['maxScore'],
            'integrity' => $filters['integrity'] !== 'all' ? $filters['integrity'] : null,
            'sort'      => $filters['sort'] !== 'newest' ? $filters['sort'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        return http_build_query($query);
    }

    private function validDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function dateBoundary(?string $date, string $timezone, bool $exclusiveEnd): ?string
    {
        if ($date === null) {
            return null;
        }
        try {
            $value = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone($timezone));
        } catch (Throwable) {
            $value = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
        }
        if ($exclusiveEnd) {
            $value = $value->modify('+1 day');
        }
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function scoreFilter(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        return max(0.0, min(100.0, (float) $value));
    }

    private function durationSeconds(mixed $start, mixed $end): ?int
    {
        if ($start === null || $start === '' || $end === null || $end === '') {
            return null;
        }
        try {
            return max(0, (new DateTimeImmutable((string) $end, new DateTimeZone('UTC')))->getTimestamp()
                - (new DateTimeImmutable((string) $start, new DateTimeZone('UTC')))->getTimestamp());
        } catch (Throwable) {
            return null;
        }
    }

    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }
        if ($seconds >= 3600) {
            return sprintf('%dh %02dm %02ds', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        return sprintf('%dm %02ds', intdiv($seconds, 60), $seconds % 60);
    }

    private function optionLabel(int $index): string
    {
        $value = $index + 1;
        $label = '';
        while ($value > 0) {
            $value--;
            $label = chr(65 + ($value % 26)) . $label;
            $value = intdiv($value, 26);
        }
        return $label;
    }

    /** @return array{iso: string, display: string}|null */
    private function localDate(mixed $value, string $timezone): ?array
    {
        $date = $this->localDateTime($value, $timezone);
        return $date === null ? null : [
            'iso'     => $date->format(DATE_ATOM),
            'display' => $date->format('j M Y, H:i'),
        ];
    }

    private function localIso(mixed $value, string $timezone): ?string
    {
        return $this->localDateTime($value, $timezone)?->format(DATE_ATOM);
    }

    private function localDateTime(mixed $value, string $timezone): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $target = new DateTimeZone($timezone);
        } catch (Throwable) {
            $target = new DateTimeZone('UTC');
        }
        try {
            return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->setTimezone($target);
        } catch (Throwable) {
            return null;
        }
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function decimalOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : $this->decimal((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /** @return array<mixed> */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $settings
     *  @param array<string, mixed> $attempt
     *  @return list<array{label: string, value: string}>
     */
    private function policySummary(array $settings, array $attempt, string $timezone): array
    {
        $read = static function (array $data, string ...$keys): mixed {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data)) {
                    return $data[$key];
                }
            }
            return null;
        };
        $bool = static fn (mixed $value): string => filter_var($value, FILTER_VALIDATE_BOOL) ? lang('Results.enabled') : lang('Results.disabled');
        $timer = $read($settings, 'timeLimitSec', 'time_limit_sec');

        return [
            ['label' => lang('Results.totalTimer'), 'value' => $timer === null ? lang('Results.none') : $this->formatDuration((int) $timer)],
            ['label' => lang('Results.feedbackTiming'), 'value' => (string) ($read($settings, 'feedback') ?? lang('Results.notCaptured'))],
            ['label' => lang('Results.shuffleQuestions'), 'value' => $bool($read($settings, 'shuffleQuestions', 'shuffle_questions'))],
            ['label' => lang('Results.shuffleOptions'), 'value' => $bool($read($settings, 'shuffleOptions', 'shuffle_options'))],
            ['label' => lang('Results.showScore'), 'value' => $bool($read($settings, 'showScore', 'show_score'))],
            ['label' => lang('Results.showAnswers'), 'value' => $bool($read($settings, 'showAnswers', 'show_answers'))],
            ['label' => lang('Results.showExplanations'), 'value' => $bool($read($settings, 'showExplain', 'show_explain'))],
            ['label' => lang('Results.integrityMonitoring'), 'value' => $bool($read($settings, 'cheatCheck', 'cheat_check'))],
            ['label' => lang('Results.capturedClosingTime'), 'value' => $this->localDate($attempt['closesAt'] ?? null, $timezone)['display'] ?? lang('Results.none')],
        ];
    }

    /** @return array<string, mixed> */
    private function sanitizeMetadata(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            $label = (string) $key;
            if (preg_match('/token|hash|passcode|credential|private.*path|start[_-]?key|submit(?:mission)?[_-]?key/i', $label)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$label] = $this->sanitizeMetadata($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$label] = $value;
            }
        }
        return $safe;
    }

    private function paperMedia(array $paper, mixed $media): ?array
    {
        if (! is_array($media) || ! isset($media['type'], $media['src'], $media['key'])) {
            return null;
        }
        return $this->media()->paperDescriptor(
            (string) $media['type'], (string) $media['src'],
            (string) $paper['public_id'], (string) $media['key'],
        );
    }

    private function papers(): QuizPaperService
    {
        return $this->papers ?? service('quizPapers');
    }

    private function media(): MediaService
    {
        return $this->media ?? service('media');
    }

    private function resolveOverdue(int $quizId): void
    {
        (new \App\Services\Player\PlayerRuntime($this->db, $this->media(), $this->papers()))
            ->assessment->resolveQuizOverdue($quizId);
    }

    private function restoreAliases(string ...$aliases): void
    {
        foreach ($aliases as $alias) {
            $this->db->addTableAlias($alias);
        }
    }
}
