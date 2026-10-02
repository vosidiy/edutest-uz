<?php

declare(strict_types=1);

namespace Tests\App\Services;

use Tests\Support\PlayerTestCase;

/** Only runs on the explicitly opted-in, guarded temporary MySQL server. */
final class QuitAttemptUpgradeTest extends PlayerTestCase
{
    public function testConstraintOnlyUpgradePreservesRecordsAndAllowsQuit(): void
    {
        if (! getenv('EDUTEST_TEST_MYSQL_SOCKET')) {
            self::markTestSkipped('Requires isolated MySQL.');
        }

        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        $this->db->query('ALTER TABLE attempts DROP CHECK chk_attempts_ended_reason');
        $this->db->query("ALTER TABLE attempts ADD CONSTRAINT chk_attempts_ended_reason CHECK (ended_reason IS NULL OR ended_reason IN ('completed', 'timer_expired', 'scheduled_close', 'stale_timeout'))");

        $before = [];
        foreach (['users', 'quizzes', 'questions', 'question_options', 'quiz_papers', 'attempts', 'attempt_answers', 'practice_keys', 'cheat_events'] as $table) {
            $before[$table] = $this->db->table($table)->get()->getResultArray();
        }

        $sql = preg_replace('/^--.*$/m', '', file_get_contents(ROOTPATH . 'docs/quit-attempt-upgrade.sql'));
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                $this->db->query(trim($statement));
            }
        }

        foreach ($before as $table => $rows) {
            self::assertSame($rows, $this->db->table($table)->get()->getResultArray(), $table . ' changed during constraint upgrade');
        }

        $done = $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['credential'],
            $this->finishBody($attempt, 'quit', count: 0),
        );
        self::assertSame('quit', $done['finishReason']);
    }
}
