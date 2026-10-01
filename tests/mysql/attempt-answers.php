<?php

declare(strict_types=1);

// Standalone, opt-in check against a dedicated temporary MySQL server only.
// php tests/mysql/attempt-answers.php /private/tmp/edutest-responses-mysql-XXXXXX/mysql.sock

use Tests\Support\IsolatedMysql;

chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/util_bootstrap.php';

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function applySql($db, string $sql): void
{
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') {
            $db->query(trim($statement));
        }
    }
}

function percentile(array $values, float $percentile): float
{
    sort($values);
    $index = (int) floor((count($values) - 1) * $percentile);
    return round($values[$index], 3);
}

$isolated = new IsolatedMysql($argv[1] ?? '');
$db = $isolated->connect();

try {
    applySql($db, file_get_contents('docs/schema.sql'));

    $attemptColumns = array_column($db->query('SHOW COLUMNS FROM attempts')->getResultArray(), 'Field');
    check(! in_array('responses', $attemptColumns, true), 'Obsolete attempts.responses column remains.');
    check(! in_array('settings', $attemptColumns, true), 'Obsolete attempts.settings column remains.');
    check(! in_array('phase', $attemptColumns, true), 'Obsolete attempts.phase column remains.');
    check(! in_array('version', $attemptColumns, true), 'Obsolete attempts.version column remains.');
    check(in_array('late_sync', $attemptColumns, true), 'Missing late synchronization flag.');
    check(! $db->tableExists('attempt_items'), 'Obsolete attempt_items table remains.');
    check($db->tableExists('attempt_answers'), 'Canonical attempt_answers table is missing.');

    $answerColumns = array_column($db->query('SHOW COLUMNS FROM attempt_answers')->getResultArray(), 'Field');
    foreach (['attempt_id', 'question_id', 'pos', 'presented_option_codes', 'status', 'selected_option_codes', 'text_answer', 'is_correct', 'answered_at'] as $column) {
        check(in_array($column, $answerColumns, true), "Missing attempt_answers.{$column}.");
    }

    $db->query("SET SESSION information_schema_stats_expiry = 0");
    $measurements = [];
    foreach ([20, 100, 500] as $questions) {
        $db->query('CREATE TABLE bench_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            public_id CHAR(32) NOT NULL,
            status VARCHAR(20) NOT NULL,
            score SMALLINT UNSIGNED NULL,
            max_score SMALLINT UNSIGNED NOT NULL,
            percent DECIMAL(5,2) NULL,
            started_at DATETIME(6) NOT NULL,
            finished_at DATETIME(6) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
        $db->query('CREATE TABLE bench_attempt_answers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id VARCHAR(32) NOT NULL,
            pos SMALLINT UNSIGNED NOT NULL,
            presented_option_codes JSON NOT NULL,
            status VARCHAR(20) NOT NULL,
            selected_option_codes JSON NULL,
            text_answer VARCHAR(500) NULL,
            is_correct TINYINT(1) NULL,
            answered_at DATETIME(6) NULL,
            UNIQUE KEY uq_bench_question (attempt_id, question_id),
            UNIQUE KEY uq_bench_position (attempt_id, pos),
            CONSTRAINT fk_bench_attempt FOREIGN KEY (attempt_id) REFERENCES bench_attempts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');

        $now = '2026-01-01 00:00:00.000000';
        for ($attempt = 1; $attempt <= 200; $attempt++) {
            $db->table('bench_attempts')->insert([
                'public_id' => substr(hash('sha256', 'attempt:' . $attempt), 0, 32),
                'status' => 'in_progress',
                'score' => null,
                'max_score' => $questions,
                'percent' => null,
                'started_at' => $now,
            ]);
            $attemptId = (int) $db->insertID();
            $rows = [];
            for ($position = 1; $position <= $questions; $position++) {
                $codes = array_map(
                    static fn (int $index): string => substr(hash('sha256', "choice:{$position}:{$index}"), 0, 16),
                    range(1, 4),
                );
                $rows[] = [
                    'attempt_id' => $attemptId,
                    'question_id' => (string) $position,
                    'pos' => $position,
                    'presented_option_codes' => json_encode($codes, JSON_THROW_ON_ERROR),
                    'status' => 'not_reached',
                    'selected_option_codes' => null,
                    'text_answer' => null,
                    'is_correct' => null,
                    'answered_at' => null,
                ];
            }
            $db->table('bench_attempt_answers')->insertBatch($rows);
        }

        foreach (['bench_attempts', 'bench_attempt_answers'] as $table) {
            $db->query('ANALYZE TABLE ' . $table);
        }
        $sizes = [];
        foreach ($db->query(
            'SELECT TABLE_NAME, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?,?)',
            [$isolated->database, 'bench_attempts', 'bench_attempt_answers'],
        )->getResultArray() as $row) {
            $sizes[$row['TABLE_NAME']] = (int) $row['bytes'];
        }

        $elapsed = [];
        for ($iteration = 0; $iteration < 50; $iteration++) {
            $start = hrtime(true);
            $db->transBegin();
            $db->query('SELECT id FROM bench_attempts WHERE id = 1 FOR UPDATE');
            $db->table('bench_attempt_answers')
                ->where('attempt_id', 1)
                ->where('pos', min($questions, $iteration + 1))
                ->where('status', 'not_reached')
                ->update([
                    'status' => 'answered',
                    'selected_option_codes' => json_encode([substr(hash('sha256', 'choice:' . min($questions, $iteration + 1) . ':1'), 0, 16)], JSON_THROW_ON_ERROR),
                    'is_correct' => 1,
                    'answered_at' => '2026-01-01 00:01:00.000000',
                ]);
            $db->query('UPDATE bench_attempts SET score = COALESCE(score, 0) + 1 WHERE id = 1');
            $db->transCommit();
            $elapsed[] = (hrtime(true) - $start) / 1e6;
        }

        $measurements[] = [
            'questions' => $questions,
            'attempts' => 200,
            'answerRows' => 200 * $questions,
            'attemptBytes' => $sizes['bench_attempts'] ?? 0,
            'answerBytes' => $sizes['bench_attempt_answers'] ?? 0,
            'totalBytes' => ($sizes['bench_attempts'] ?? 0) + ($sizes['bench_attempt_answers'] ?? 0),
            'confirmMedianMs' => percentile($elapsed, 0.50),
            'confirmP95Ms' => percentile($elapsed, 0.95),
        ];

        foreach (['bench_attempt_answers', 'bench_attempts'] as $table) {
            $db->query('DROP TABLE ' . $table);
        }
    }

    echo json_encode([
        'mysql' => $db->query('SELECT VERSION() AS v')->getRow('v'),
        'measurements' => $measurements,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} finally {
    $db->close();
    $isolated->close();
}
