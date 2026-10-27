<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReportingException;
use App\Services\Player\PlayerRuntime;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;

final class TeacherResultsService
{
    private const PAGE_SIZE = 25;
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
        $rows = $this->selectRows($this->attemptBuilder((int) $quiz['id'], $filters))
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

        return [
            'quiz' => [
                'publicId'  => (string) $quiz['public_id'],
                'title'     => trim((string) $quiz['title']) === '' ? lang('Results.untitledQuiz') : (string) $quiz['title'],
                'builderUrl'=> $quiz['deleted_at'] === null ? site_url('quizzes/' . $quiz['public_id'] . '/edit') : null,
                'restoreUrl'=> site_url('dashboard?status=trash'),
                'status'    => $quiz['deleted_at'] === null ? (string) $quiz['status'] : 'deleted',
            ],
            'attempts'   => $attempts,
            'filters'    => $filters,
            'pagination' => ['page' => $filters['page'], 'pageSize' => self::PAGE_SIZE, 'total' => $total, 'pageCount' => $pageCount],
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

    private function attemptBuilder(int $quizId, array $filters): BaseBuilder
    {
        $builder = $this->db->table('attempts a')->where('a.quiz_id', $quizId);
        if ($filters['query'] !== '') {
            $builder->groupStart()->like('a.name', $filters['query'])->orLike('a.email', $filters['query'])->orLike('a.phone', $filters['query'])->groupEnd();
        }
        if ($filters['dateFromUtc'] !== null) $builder->where('a.started_at >=', $filters['dateFromUtc']);
        if ($filters['dateToUtc'] !== null) $builder->where('a.started_at <', $filters['dateToUtc']);
        if ($filters['minScore'] !== null) $builder->where('a.percent >=', $filters['minScore']);
        return $builder;
    }

    private function selectRows(BaseBuilder $builder): BaseBuilder
    {
        return $builder->select('a.*')->orderBy('a.started_at', 'DESC')->orderBy('a.id', 'DESC');
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
        $dateRange = $string('dateRange');
        if (! in_array($dateRange, ['last_24_hours', 'yesterday', 'last_7_days', 'last_30_days', 'all'], true)) {
            $dateRange = 'all';
        }
        [$dateFromUtc, $dateToUtc] = $this->dateRangeBounds($dateRange, $timezone);
        return [
            'query'       => mb_substr($string('q'), 0, 200),
            'dateRange'   => $dateRange,
            'dateFromUtc' => $dateFromUtc,
            'dateToUtc'   => $dateToUtc,
            'minScore'    => $this->scoreFilter($input['minScore'] ?? null),
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

    private function nullableString(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : (string) $value;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : number_format((float) $value, 2, '.', '');
    }

    /** @return array{0:?string,1:?string} */
    private function dateRangeBounds(string $range, string $timezone): array
    {
        if ($range === 'all') return [null, null];

        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);
        if ($range === 'yesterday') {
            $today = $now->setTimezone(new DateTimeZone($timezone))->setTime(0, 0);
            return [
                $today->modify('-1 day')->setTimezone($utc)->format('Y-m-d H:i:s.u'),
                $today->setTimezone($utc)->format('Y-m-d H:i:s.u'),
            ];
        }

        $hours = match ($range) {
            'last_7_days'  => 7 * 24,
            'last_30_days' => 30 * 24,
            default        => 24,
        };
        return [$now->modify('-' . $hours . ' hours')->format('Y-m-d H:i:s.u'), $now->format('Y-m-d H:i:s.u')];
    }

    private function scoreFilter(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) return null;
        $score = (float) $value;
        return $score >= 0 && $score <= 100 ? $score : null;
    }
}
