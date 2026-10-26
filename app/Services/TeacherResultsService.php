<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReportingException;
use App\Services\Player\ScoringService;
use App\Services\Player\PlayerRuntime;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;

final class TeacherResultsService
{
    private const PAGE_SIZE = 25;
    private const EXPORT_BATCH_SIZE = 500;
    private readonly BaseConnection $db;

    public function __construct(
        ?BaseConnection $db = null,
        private readonly ?MediaService $media = null,
        private readonly ?AttemptSnapshotService $snapshots = null,
        private readonly ?PlayerRuntime $player = null,
    ) {
        $this->db = $db ?? Database::connect();
    }

    /** @return array<string, mixed> */
    public function overview(int $userId, array $input, string $timezone): array
    {
        $rows = $this->db->table('quizzes q')
            ->select('q.id, q.public_id, q.title, q.status, q.deleted_at, q.updated_at')
            ->select("SUM(CASE WHEN a.status IN ('completed','abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN a.status IN ('completed','abandoned') THEN a.percent ELSE NULL END) AS average_percent", false)
            ->join('attempts a', 'a.quiz_id = q.id', 'left')
            ->where('q.user_id', $userId)
            ->groupBy('q.id, q.public_id, q.title, q.status, q.deleted_at, q.updated_at')
            ->orderBy('q.updated_at', 'DESC')->get()->getResultArray();

        $aggregate = $this->db->table('attempts a')
            ->select("SUM(CASE WHEN a.status IN ('completed','abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN a.status IN ('completed','abandoned') THEN a.percent ELSE NULL END) AS average_percent", false)
            ->join('quizzes q', 'q.id = a.quiz_id')->where('q.user_id', $userId)->get()->getRowArray() ?? [];

        return [
            'metrics' => [
                'finalizedAttempts' => (int) ($aggregate['finalized_count'] ?? 0),
                'inProgressAttempts'=> (int) ($aggregate['in_progress_count'] ?? 0),
                'averagePercent'    => $this->decimalOrNull($aggregate['average_percent'] ?? null),
            ],
            'rows' => array_map(fn (array $row): array => [
                'publicId'        => (string) $row['public_id'],
                'title'           => (string) $row['title'],
                'status'          => $row['deleted_at'] === null ? (string) $row['status'] : 'deleted',
                'finalizedCount'  => (int) $row['finalized_count'],
                'inProgressCount' => (int) $row['in_progress_count'],
                'averagePercent'  => $this->decimalOrNull($row['average_percent']),
                'updatedAt'       => $this->localDate($row['updated_at'], $timezone),
                'url'             => site_url('results/quizzes/' . $row['public_id']),
            ], $rows),
            'filters' => [],
            'pagination' => ['page' => 1, 'pageSize' => count($rows), 'total' => count($rows), 'pageCount' => 1],
        ];
    }

    /** @return array<string, mixed> */
    public function quiz(int $userId, string $quizPublicId, array $input, string $timezone): array
    {
        $quiz = $this->ownedQuiz($userId, $quizPublicId);
        ($this->player ?? service('player'))->assessment->resolveQuizOverdue((int) $quiz['id']);
        $filters = $this->normalizeFilters($input, $timezone);
        $total = $this->attemptBuilder((int) $quiz['id'], $filters)->countAllResults();
        $pageCount = max(1, (int) ceil($total / self::PAGE_SIZE));
        $filters['page'] = min($filters['page'], $pageCount);
        $rows = $this->selectRows($this->attemptBuilder((int) $quiz['id'], $filters), $filters['sort'])
            ->limit(self::PAGE_SIZE, ($filters['page'] - 1) * self::PAGE_SIZE)
            ->get()->getResultArray();

        $attempts = [];
        foreach ($rows as $row) {
            $snapshot = $this->decodeSnapshot($row['snapshot']);
            $answers = $this->decodeAnswers($row['answers']);
            $correct = 0;
            foreach ($answers as $index => $answer) {
                if (isset($snapshot['questions'][$index])
                    && $answer['answerCode'] !== null
                    && hash_equals((string) $snapshot['questions'][$index]['correctCode'], (string) $answer['answerCode'])) {
                    $correct++;
                }
            }
            $attempts[] = $this->attemptRow($row, count($answers), $correct, $timezone);
        }

        $metrics = $this->db->table('attempts')
            ->select("SUM(CASE WHEN status IN ('completed','abandoned') THEN 1 ELSE 0 END) AS finalized_count", false)
            ->select("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count", false)
            ->select("AVG(CASE WHEN status IN ('completed','abandoned') THEN percent ELSE NULL END) AS average_percent", false)
            ->where('quiz_id', $quiz['id'])->get()->getRowArray() ?? [];

        $query = http_build_query(array_filter([
            'q' => $filters['query'], 'status' => $filters['status'], 'dateFrom' => $filters['dateFrom'],
            'dateTo' => $filters['dateTo'], 'minScore' => $filters['minScore'], 'maxScore' => $filters['maxScore'],
            'sort' => $filters['sort'],
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== 'all'));

        return [
            'quiz' => [
                'publicId'  => (string) $quiz['public_id'],
                'title'     => trim((string) $quiz['title']) === '' ? lang('Results.untitledQuiz') : (string) $quiz['title'],
                'builderUrl'=> $quiz['deleted_at'] === null ? site_url('quizzes/' . $quiz['public_id'] . '/edit') : null,
                'restoreUrl'=> site_url('dashboard?status=trash'),
                'status'    => $quiz['deleted_at'] === null ? (string) $quiz['status'] : 'deleted',
            ],
            'metrics' => [
                'finalizedAttempts' => (int) ($metrics['finalized_count'] ?? 0),
                'inProgressAttempts'=> (int) ($metrics['in_progress_count'] ?? 0),
                'averagePercent'    => $this->decimalOrNull($metrics['average_percent'] ?? null),
            ],
            'attempts'   => $attempts,
            'filters'    => $filters,
            'pagination' => ['page' => $filters['page'], 'pageSize' => self::PAGE_SIZE, 'total' => $total, 'pageCount' => $pageCount],
            'exportUrl'  => site_url('results/quizzes/' . $quiz['public_id'] . '/export.csv') . ($query === '' ? '' : '?' . $query),
        ];
    }

    /** @return array<string, mixed> */
    public function attempt(int $userId, string $attemptPublicId, string $timezone): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $attemptPublicId) !== 1) {
            throw new ReportingException('attempt_not_found', 'Attempt not found.');
        }
        $row = $this->db->table('attempts a')
            ->select('a.*, q.public_id AS quiz_public_id, q.title AS quiz_title, q.status AS quiz_status, q.deleted_at AS quiz_deleted_at')
            ->join('quizzes q', 'q.id = a.quiz_id')
            ->where('a.public_id', $attemptPublicId)->where('q.user_id', $userId)
            ->get()->getRowArray();
        if ($row === null) {
            throw new ReportingException('attempt_not_found', 'Attempt not found.');
        }
        ($this->player ?? service('player'))->assessment->resolveQuizOverdue((int) $row['quiz_id']);
        $fresh = $this->db->table('attempts')->where('id', $row['id'])->get()->getRowArray();
        if ($fresh !== null) $row = array_replace($row, $fresh);

        $snapshot = $this->decodeSnapshot($row['snapshot']);
        $answers = $this->decodeAnswers($row['answers']);
        $questions = [];
        foreach ($snapshot['questions'] as $index => $question) {
            $answer = $answers[$index] ?? ['answerCode' => null, 'answeredAt' => null];
            $selectedCode = $answer['answerCode'];
            $correct = $selectedCode !== null && hash_equals((string) $question['correctCode'], (string) $selectedCode);
            $options = [];
            foreach ($question['options'] as $optionIndex => $option) {
                $options[] = [
                    'label'    => $this->optionLabel($optionIndex),
                    'content'  => (string) $option['content'],
                    'selected' => $selectedCode !== null && hash_equals((string) $option['code'], (string) $selectedCode),
                    'correct'  => hash_equals((string) $question['correctCode'], (string) $option['code']),
                    'media'    => $this->attemptMedia($row, $option['media'] ?? null),
                ];
            }
            $confirmed = array_key_exists($index, $answers);
            $questions[] = [
                'position'        => $index + 1,
                'type'            => 'single_choice',
                'optionsLayout'   => (string) ($question['optionsLayout'] ?? 'list'),
                'content'         => (string) $question['content'],
                'explanation'     => $this->nullableString($question['explanation'] ?? null),
                'status'          => $confirmed ? 'locked' : ($row['status'] === 'in_progress' && $index === count($answers) ? 'active' : 'pending'),
                'result'          => ! $confirmed || $answer['answerCode'] === null ? ($confirmed ? 'skipped' : null) : ($correct ? 'correct' : 'wrong'),
                'answer'          => $selectedCode,
                'answeredAt'      => $this->localDate($answer['answeredAt'], $timezone),
                'clientAnsweredAt'=> $this->localDate($answer['answeredAt'], $timezone),
                'correctAnswer'   => (string) $question['correctCode'],
                'options'         => $options,
                'media'           => $this->attemptMedia($row, $question['media'] ?? null),
            ];
        }

        $duration = $this->durationSeconds($row['started_at'], $row['finished_at'] ?? $row['last_saved_at']);
        $quizSnapshot = $snapshot['quiz'];
        return [
            'attempt' => [
                'publicId'    => (string) $row['public_id'],
                'name'        => (string) $row['name'],
                'email'       => $this->nullableString($row['email']),
                'phone'       => $this->nullableString($row['phone']),
                'ip'          => $this->nullableString($row['ip']),
                'agent'       => $this->nullableString($row['agent']),
                'status'      => (string) $row['status'],
                'finishReason'=> $this->nullableString($row['ended_reason']),
                'lateSync'    => (bool) $row['late_sync'],
                'score'       => $this->decimalOrNull($row['score']),
                'maxScore'    => (string) (int) $row['max_score'],
                'percent'     => $this->decimalOrNull($row['percent']),
                'startedAt'   => $this->localDate($row['started_at'], $timezone),
                'submittedAt' => $this->localDate($row['finished_at'], $timezone),
                'updatedAt'   => $this->localDate($row['last_saved_at'], $timezone),
                'expiresAt'   => $this->localDate($row['expires_at'], $timezone),
                'durationSec' => $duration,
                'duration'    => $this->formatDuration($duration),
            ],
            'quiz' => [
                'publicId'  => (string) $row['quiz_public_id'],
                'title'     => (string) $row['quiz_title'],
                'builderUrl'=> $row['quiz_deleted_at'] === null ? site_url('quizzes/' . $row['quiz_public_id'] . '/edit') : null,
                'restoreUrl'=> site_url('dashboard?status=trash'),
                'status'    => $row['quiz_deleted_at'] === null ? (string) $row['quiz_status'] : 'deleted',
                'url'       => site_url('results/quizzes/' . $row['quiz_public_id']),
            ],
            'snapshot' => [
                'title'       => (string) $quizSnapshot['title'],
                'description' => (string) $quizSnapshot['description'],
            ],
            'policies' => [
                ['label' => 'Timer', 'value' => $this->formatDuration((int) $quizSnapshot['timeLimitSec'])],
                ['label' => 'Feedback', 'value' => $quizSnapshot['feedback'] === 'after_each' ? 'After each question' : 'At the end'],
                ['label' => 'Show score', 'value' => $quizSnapshot['showScore'] ? 'Yes' : 'No'],
                ['label' => 'Show correct answers', 'value' => $quizSnapshot['showAnswers'] ? 'Yes' : 'No'],
                ['label' => 'Show explanations', 'value' => $quizSnapshot['showExplain'] ? 'Yes' : 'No'],
            ],
            'questions' => $questions,
            'timezone'  => $timezone,
        ];
    }

    /** @return array<string, mixed> */
    public function export(int $userId, string $quizPublicId, array $input, string $timezone): array
    {
        $quiz = $this->ownedQuiz($userId, $quizPublicId);
        ($this->player ?? service('player'))->assessment->resolveQuizOverdue((int) $quiz['id']);
        $filters = $this->normalizeFilters($input, $timezone);
        return [
            'quiz' => ['publicId' => (string) $quiz['public_id'], 'title' => (string) $quiz['title']],
            'filters' => $filters,
            'rows' => $this->exportRows((int) $quiz['id'], $filters, $timezone),
        ];
    }

    public function protectCsvCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

    private function exportRows(int $quizId, array $filters, string $timezone): iterable
    {
        $offset = 0;
        do {
            $rows = $this->selectRows($this->attemptBuilder($quizId, $filters), $filters['sort'])
                ->limit(self::EXPORT_BATCH_SIZE, $offset)->get()->getResultArray();
            foreach ($rows as $row) {
                $duration = $this->durationSeconds($row['started_at'], $row['finished_at'] ?? $row['last_saved_at']);
                yield [
                    'attemptPublicId' => (string) $row['public_id'],
                    'name'            => (string) $row['name'],
                    'email'           => $this->nullableString($row['email']) ?? '',
                    'phone'           => $this->nullableString($row['phone']) ?? '',
                    'status'          => (string) $row['status'],
                    'finishReason'    => $this->nullableString($row['ended_reason']) ?? '',
                    'score'           => $this->decimalOrNull($row['score']) ?? '',
                    'maxScore'        => (string) (int) $row['max_score'],
                    'percentage'      => $this->decimalOrNull($row['percent']) ?? '',
                    'startedAt'       => $this->localIso($row['started_at'], $timezone) ?? '',
                    'submittedAt'     => $this->localIso($row['finished_at'], $timezone) ?? '',
                    'durationSeconds' => $duration === null ? '' : (string) $duration,
                    'lateSync'        => (bool) $row['late_sync'] ? 'yes' : 'no',
                ];
            }
            $offset += count($rows);
        } while (count($rows) === self::EXPORT_BATCH_SIZE);
    }

    private function attemptBuilder(int $quizId, array $filters): BaseBuilder
    {
        $builder = $this->db->table('attempts a')->where('a.quiz_id', $quizId);
        match ($filters['status']) {
            'finalized'   => $builder->whereIn('a.status', ['completed', 'abandoned']),
            'in_progress' => $builder->where('a.status', 'in_progress'),
            'completed'   => $builder->where('a.status', 'completed'),
            'abandoned'   => $builder->where('a.status', 'abandoned'),
            default       => null,
        };
        if ($filters['query'] !== '') {
            $builder->groupStart()->like('a.name', $filters['query'])->orLike('a.email', $filters['query'])->orLike('a.phone', $filters['query'])->groupEnd();
        }
        if ($filters['dateFromUtc'] !== null) $builder->where('a.started_at >=', $filters['dateFromUtc']);
        if ($filters['dateToUtc'] !== null) $builder->where('a.started_at <', $filters['dateToUtc']);
        if ($filters['minScore'] !== null) $builder->where('a.percent >=', $filters['minScore']);
        if ($filters['maxScore'] !== null) $builder->where('a.percent <=', $filters['maxScore']);
        return $builder;
    }

    private function selectRows(BaseBuilder $builder, string $sort): BaseBuilder
    {
        $builder->select('a.*');
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
    private function attemptRow(array $row, int $responses, int $correct, string $timezone): array
    {
        $duration = $this->durationSeconds($row['started_at'], $row['finished_at'] ?? $row['last_saved_at']);
        return [
            'publicId'         => (string) $row['public_id'],
            'name'             => (string) $row['name'],
            'email'            => $this->nullableString($row['email']),
            'phone'            => $this->nullableString($row['phone']),
            'status'           => (string) $row['status'],
            'finishReason'     => $this->nullableString($row['ended_reason']),
            'lateSync'         => (bool) $row['late_sync'],
            'responsesReceived'=> $responses,
            'correctReceived'  => $correct,
            'score'            => $this->decimalOrNull($row['score']),
            'maxScore'         => (string) (int) $row['max_score'],
            'percent'          => $this->decimalOrNull($row['percent']),
            'startedAt'        => $this->localDate($row['started_at'], $timezone),
            'submittedAt'      => $this->localDate($row['finished_at'], $timezone),
            'durationSec'      => $duration,
            'duration'         => $this->formatDuration($duration),
            'url'              => site_url('results/attempts/' . $row['public_id']),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeFilters(array $input, string $timezone): array
    {
        $string = static fn (string $key): string => is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';
        $status = $string('status');
        $sort = $string('sort');
        $dateFrom = $this->validDate($string('dateFrom'));
        $dateTo = $this->validDate($string('dateTo'));
        return [
            'query'       => mb_substr($string('q'), 0, 200),
            'status'      => in_array($status, ['all', 'finalized', 'in_progress', 'completed', 'abandoned'], true) ? $status : 'all',
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'dateFromUtc' => $dateFrom === null ? null : $this->dateBoundary($dateFrom, $timezone, false),
            'dateToUtc'   => $dateTo === null ? null : $this->dateBoundary($dateTo, $timezone, true),
            'minScore'    => $this->scoreFilter($input['minScore'] ?? null),
            'maxScore'    => $this->scoreFilter($input['maxScore'] ?? null),
            'sort'        => in_array($sort, ['newest', 'oldest', 'score_desc', 'score_asc', 'name_asc'], true) ? $sort : 'newest',
            'page'        => max(1, (int) (is_scalar($input['page'] ?? null) ? $input['page'] : 1)),
        ];
    }

    private function ownedQuiz(int $userId, string $publicId): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $publicId) !== 1) throw new ReportingException('quiz_not_found', 'Quiz not found.');
        $row = $this->db->table('quizzes')->where('public_id', $publicId)->where('user_id', $userId)->get()->getRowArray();
        if ($row === null) throw new ReportingException('quiz_not_found', 'Quiz not found.');
        return $row;
    }

    private function decodeSnapshot(mixed $value): array
    {
        try {
            return ($this->snapshots ?? service('attemptSnapshots'))->decode(is_array($value) ? $value : (string) $value);
        } catch (\Throwable) {
            throw new ReportingException('attempt_not_found', 'Attempt snapshot is invalid.');
        }
    }

    private function decodeAnswers(mixed $value): array
    {
        $answers = is_array($value) ? $value : json_decode((string) $value, true);
        return is_array($answers) && array_is_list($answers) ? $answers : [];
    }

    private function attemptMedia(array $attempt, mixed $stored): ?array
    {
        if (! is_array($stored) || ! is_string($stored['type'] ?? null) || ! is_string($stored['src'] ?? null) || ! is_string($stored['key'] ?? null)) return null;
        return ($this->media ?? service('media'))->attemptDescriptor($stored['type'], $stored['src'], (string) $attempt['public_id'], $stored['key']);
    }

    private function optionLabel(int $index): string
    {
        $label = '';
        do {
            $label = chr(65 + ($index % 26)) . $label;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $label;
    }

    private function durationSeconds(mixed $start, mixed $end): ?int
    {
        if ($start === null || $end === null) return null;
        return max(0, (new DateTimeImmutable((string) $end, new DateTimeZone('UTC')))->getTimestamp()
            - (new DateTimeImmutable((string) $start, new DateTimeZone('UTC')))->getTimestamp());
    }

    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) return null;
        return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    private function localDate(mixed $value, string $timezone): ?array
    {
        if ($value === null || $value === '') return null;
        $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        $local = $date->setTimezone(new DateTimeZone($timezone));
        return ['display' => $local->format('d M Y, H:i:s'), 'iso' => $local->format(DATE_ATOM)];
    }

    private function localIso(mixed $value, string $timezone): ?string
    {
        return $this->localDate($value, $timezone)['iso'] ?? null;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : (string) $value;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : number_format((float) $value, 2, '.', '');
    }

    private function validDate(string $value): ?string
    {
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function dateBoundary(string $date, string $timezone, bool $nextDay): string
    {
        $value = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone($timezone));
        if ($nextDay) $value = $value->modify('+1 day');
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function scoreFilter(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) return null;
        $score = (float) $value;
        return $score >= 0 && $score <= 100 ? $score : null;
    }
}
