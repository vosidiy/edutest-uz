<?php

declare(strict_types=1);

namespace Tests\App\Services;

use Tests\Support\PlayerTestCase;

/** Only runs on the explicitly opted-in, guarded temporary MySQL server. */
final class OfflineUpgradeTest extends PlayerTestCase
{
    public function testAdditiveUpgradePreservesExistingRecordsAndUnknownOccurrenceTimes(): void
    {
        if (! getenv('EDUTEST_TEST_MYSQL_SOCKET')) self::markTestSkipped('Requires isolated MySQL.');
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        $this->player->assessment->answer($attempt['attemptId'], $attempt['items'][0]['questionId'], $attempt['credential'], $this->confirmation($this->submission($attempt, 0)));
        // Reconstruct the preceding schema only inside this disposable database.
        $this->db->query('ALTER TABLE attempts DROP CHECK chk_attempts_late_sync, DROP COLUMN late_sync, DROP COLUMN client_activity_at');
        $this->db->query('ALTER TABLE attempt_answers DROP COLUMN client_answered_at');
        $before = [];
        foreach (['users', 'quizzes', 'questions', 'question_options', 'quiz_papers', 'attempts', 'attempt_answers', 'practice_keys', 'cheat_events'] as $table) {
            $before[$table] = $this->db->table($table)->get()->getResultArray();
        }
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(ROOTPATH . 'docs/offline-continuity-upgrade.sql'));
        foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $this->db->query(trim($statement));
        foreach ($before as $table => $rows) {
            $after = $this->db->table($table)->get()->getResultArray();
            foreach ($after as &$row) {
                if ($table === 'attempts') {
                    self::assertSame($row['last_activity_at'], $row['client_activity_at']);
                    self::assertSame('0', (string) $row['late_sync']);
                    unset($row['client_activity_at'], $row['late_sync']);
                } elseif ($table === 'attempt_answers') {
                    self::assertNull($row['client_answered_at']);
                    unset($row['client_answered_at']);
                }
            }
            unset($row);
            self::assertSame($rows, $after, $table . ' changed during additive upgrade');
        }
        self::assertSame($attempt['attemptId'], $this->player->assessment->load($attempt['attemptId'], $attempt['credential'])['attemptId']);
    }
}
