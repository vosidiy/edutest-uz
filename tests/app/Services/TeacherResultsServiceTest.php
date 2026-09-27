<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\ReportingException;
use App\Services\MediaService;
use App\Services\QuizPaperService;
use App\Services\TeacherResultsService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class TeacherResultsServiceTest extends CIUnitTestCase
{
    private BaseConnection $reportingDb;
    private TeacherResultsService $results;
    private string $quizPublicId;
    private string $submittedAttemptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportingDb = Database::connect('tests');
        $this->dropTables();
        $this->createTables();
        $this->seed();
        $this->results = new TeacherResultsService(
            $this->reportingDb,
            new MediaService($this->reportingDb, WRITEPATH . 'testing/report-media'),
            new QuizPaperService($this->reportingDb, new MediaService($this->reportingDb, WRITEPATH . 'testing/report-media')),
        );
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }

    public function testOverviewIncludesHistoricalAssessmentQuizzesAndOwnerMetrics(): void
    {
        $report = $this->results->overview(1, [], 'Asia/Tashkent');

        $this->assertSame(4, $report['metrics']['finalizedAttempts']);
        $this->assertSame(1, $report['metrics']['inProgressAttempts']);
        $this->assertSame('78.75', $report['metrics']['averagePercent']);
        $this->assertSame(4, $report['pagination']['total']);
        $this->assertContains('archived', array_column($report['rows'], 'status'));
        $this->assertContains('deleted', array_column($report['rows'], 'status'));

        $deleted = $this->results->overview(1, ['lifecycle' => 'deleted'], 'Asia/Tashkent');
        $this->assertSame(1, $deleted['pagination']['total']);
        $this->assertSame('deleted', $deleted['rows'][0]['status']);

        $other = $this->results->overview(2, [], 'UTC');
        $this->assertSame(1, $other['metrics']['finalizedAttempts']);
        $this->assertSame(1, $other['pagination']['total']);
    }

    public function testQuizTotalsAndFiltersUseTeacherDates(): void
    {
        $report = $this->results->quiz(1, $this->quizPublicId, [], 'Asia/Tashkent');

        $this->assertSame(2, $report['metrics']['finalizedAttempts']);
        $this->assertSame(1, $report['metrics']['inProgressAttempts']);
        $this->assertSame('62.50', $report['metrics']['averagePercent']);
        $this->assertCount(2, $report['attempts']);
        $this->assertArrayNotHasKey('distribution', $report);
        $this->assertArrayNotHasKey('questions', $report);
        $this->assertSame(7, $report['attempts'][0]['paperRevision']);

        $filtered = $this->results->quiz(1, $this->quizPublicId, [
            'status' => 'all',
            'dateFrom' => '2026-01-02',
            'dateTo' => '2026-01-02',
        ], 'Asia/Tashkent');
        $this->assertSame(1, $filtered['pagination']['total']);
        $this->assertSame('Bob Student', $filtered['attempts'][0]['name']);

        $flagged = $this->results->quiz(1, $this->quizPublicId, [
            'status' => 'all',
            'integrity' => 'flagged',
        ], 'UTC');
        $this->assertSame(1, $flagged['pagination']['total']);
        $this->assertSame('=Alice Student', $flagged['attempts'][0]['name']);
    }

    public function testAssessmentHistoryRemainsAvailableWhenCurrentModeIsPractice(): void
    {
        $this->reportingDb->table('quizzes')->where('id', 1)->update(['mode' => 'practice']);

        $overview = $this->results->overview(1, [], 'UTC');
        $row = array_values(array_filter($overview['rows'], fn (array $quiz): bool => $quiz['publicId'] === $this->quizPublicId))[0] ?? null;
        $this->assertNotNull($row);
        $this->assertSame('practice', $row['currentMode']);
        $this->assertSame(4, $overview['metrics']['finalizedAttempts']);

        $quiz = $this->results->quiz(1, $this->quizPublicId, [], 'UTC');
        $this->assertSame('practice', $quiz['quiz']['currentMode']);
        $this->assertSame(2, $quiz['metrics']['finalizedAttempts']);

        $attempt = $this->results->attempt(1, $this->submittedAttemptId, 'UTC');
        $this->assertSame('practice', $attempt['quiz']['currentMode']);
        $this->assertSame('assessment', $attempt['paper']['mode']);
        $this->assertCount(1, iterator_to_array($this->results->export(1, $this->quizPublicId, ['status' => 'submitted', 'q' => 'Alice'], 'UTC')['rows']));

        try {
            $this->results->quiz(1, str_repeat('c', 32), [], 'UTC');
            $this->fail('A current Practice quiz without assessment history should not appear in Results.');
        } catch (ReportingException $exception) {
            $this->assertSame('quiz_not_found', $exception->errorCode);
        }
    }

    public function testAttemptReviewPreservesStoredChoiceOrderAndRedactsCredentialMetadata(): void
    {
        $this->reportingDb->table('question_options')->where('question_id', 1)->delete();
        $this->reportingDb->table('questions')->where('quiz_id', 1)->delete();
        $report = $this->results->attempt(1, $this->submittedAttemptId, 'Asia/Tashkent');

        $this->assertSame($this->submittedAttemptId, $report['attempt']['publicId']);
        $this->assertSame(7, $report['attempt']['paperRevision']);
        $this->assertSame('Historical assessment title', $report['paper']['title']);
        $this->assertSame('Choose the historical correct answer.', $report['questions'][0]['content']);
        $this->assertSame('203.0.113.7', $report['attempt']['ip']);
        $this->assertSame('Wrong answer', $report['questions'][0]['options'][0]['content']);
        $this->assertSame('A', $report['questions'][0]['options'][0]['label']);
        $this->assertSame('Correct answer', $report['questions'][0]['options'][1]['content']);
        $this->assertSame('B', $report['questions'][0]['options'][1]['label']);
        $this->assertTrue($report['questions'][0]['options'][1]['selected']);
        $this->assertTrue($report['questions'][0]['options'][1]['correct']);
        $this->assertArrayNotHasKey('tokenHash', $report['events'][0]['metadata']);
        $this->assertSame('hidden', $report['events'][0]['metadata']['visibilityState']);
        $this->assertArrayNotHasKey('id', $report['attempt']);
        $this->assertArrayNotHasKey('quizId', $report['attempt']);
    }

    public function testMalformedAndNonOwnedIdentifiersAreIndistinguishableNotFound(): void
    {
        foreach (['bad-id', str_repeat('b', 32)] as $publicId) {
            try {
                $this->results->quiz(1, $publicId, [], 'UTC');
                $this->fail('Unknown and non-owned quizzes must not load.');
            } catch (ReportingException $exception) {
                $this->assertSame(404, $exception->status);
                $this->assertSame('quiz_not_found', $exception->errorCode);
            }
        }

        $this->expectException(ReportingException::class);
        $this->results->attempt(2, $this->submittedAttemptId, 'UTC');
    }

    public function testExportUsesTheSameFiltersAndExcludesSensitiveFields(): void
    {
        $export = $this->results->export(1, $this->quizPublicId, [
            'status' => 'submitted',
            'q' => 'Alice',
        ], 'Asia/Tashkent');
        $rows = iterator_to_array($export['rows']);

        $this->assertCount(1, $rows);
        $this->assertSame($this->submittedAttemptId, $rows[0]['attemptPublicId']);
        $this->assertSame('=Alice Student', $rows[0]['name']);
        $this->assertSame("'=Alice Student", $this->results->protectCsvCell($rows[0]['name']));
        $this->assertSame("'+998900000000", $this->results->protectCsvCell('+998900000000'));
        $this->assertSame('ordinary', $this->results->protectCsvCell('ordinary'));
        $this->assertSame('2026-01-01T23:30:00+05:00', $rows[0]['startedAt']);
        $this->assertSame('300', $rows[0]['durationSeconds']);
        $this->assertArrayNotHasKey('ip', $rows[0]);
        $this->assertArrayNotHasKey('agent', $rows[0]);
        $this->assertArrayNotHasKey('answers', $rows[0]);
    }

    private function seed(): void
    {
        $now = '2026-01-01 00:00:00';
        $this->quizPublicId = str_repeat('a', 32);
        $quizzes = [
            ['id' => 1, 'user_id' => 1, 'public_id' => $this->quizPublicId, 'mode' => 'assessment', 'status' => 'published', 'title' => 'Active assessment', 'deleted_at' => null, 'updated_at' => '2026-01-05 00:00:00'],
            ['id' => 2, 'user_id' => 2, 'public_id' => str_repeat('b', 32), 'mode' => 'assessment', 'status' => 'published', 'title' => 'Other owner', 'deleted_at' => null, 'updated_at' => $now],
            ['id' => 3, 'user_id' => 1, 'public_id' => str_repeat('c', 32), 'mode' => 'practice', 'status' => 'published', 'title' => 'Practice', 'deleted_at' => null, 'updated_at' => $now],
            ['id' => 4, 'user_id' => 1, 'public_id' => str_repeat('d', 32), 'mode' => 'assessment', 'status' => 'archived', 'title' => 'Archived assessment', 'deleted_at' => null, 'updated_at' => $now],
            ['id' => 5, 'user_id' => 1, 'public_id' => str_repeat('e', 32), 'mode' => 'assessment', 'status' => 'closed', 'title' => 'Deleted assessment', 'deleted_at' => '2026-01-07 00:00:00', 'updated_at' => $now],
            ['id' => 6, 'user_id' => 1, 'public_id' => str_repeat('f', 32), 'mode' => 'assessment', 'status' => 'draft', 'title' => 'No attempts', 'deleted_at' => null, 'updated_at' => $now],
        ];
        $this->reportingDb->table('quizzes')->insertBatch($quizzes);
        $paperDefinition = json_encode([
            'schemaVersion' => 2,
            'quiz' => [
                'title' => 'Historical assessment title', 'description' => 'Historical description',
                'instructions' => 'Historical instructions', 'shareToken' => 'snapshot-share',
                'mode' => 'assessment', 'cover' => null, 'timeLimitSec' => 600,
                'opensAt' => null, 'closesAt' => null, 'passcodeRequired' => false,
                'emailMode' => 'optional', 'phoneMode' => 'hidden',
                'shuffleQuestions' => true, 'shuffleOptions' => false,
                'feedback' => 'at_end', 'showScore' => true, 'showAnswers' => true,
                'showExplain' => true, 'cheatCheck' => true,
                'timingPolicy' => 'client_enforced_offline_allowed',
            ],
            'questions' => [
                [
                    'id' => '1', 'position' => 1, 'type' => 'single_choice',
                    'content' => 'Choose the historical correct answer.', 'explanation' => 'A historical explanation.',
                    'acceptedAnswers' => [], 'correctCodes' => ['right'], 'media' => null,
                    'options' => [
                        ['id' => '1', 'position' => 1, 'code' => 'right', 'content' => 'Correct answer', 'isCorrect' => true, 'media' => null],
                        ['id' => '2', 'position' => 2, 'code' => 'wrong', 'content' => 'Wrong answer', 'isCorrect' => false, 'media' => null],
                    ],
                ],
                [
                    'id' => '2', 'position' => 2, 'type' => 'short_text',
                    'content' => 'Type the historical answer.', 'explanation' => '',
                    'acceptedAnswers' => ['accepted'], 'correctCodes' => [], 'media' => null, 'options' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $this->reportingDb->table('quiz_papers')->insertBatch([
            ['id' => 1, 'quiz_id' => 1, 'public_id' => str_repeat('7', 32), 'revision' => 7, 'definition' => $paperDefinition, 'created_at' => $now],
            ['id' => 2, 'quiz_id' => 4, 'public_id' => str_repeat('8', 32), 'revision' => 1, 'definition' => $paperDefinition, 'created_at' => $now],
            ['id' => 3, 'quiz_id' => 5, 'public_id' => str_repeat('9', 32), 'revision' => 1, 'definition' => $paperDefinition, 'created_at' => $now],
            ['id' => 4, 'quiz_id' => 2, 'public_id' => str_repeat('0', 32), 'revision' => 1, 'definition' => $paperDefinition, 'created_at' => $now],
        ]);


        $this->submittedAttemptId = str_repeat('1', 32);
        $settings = json_encode(['timeLimitSec' => 600, 'shuffleQuestions' => true, 'showScore' => true, 'cheatCheck' => true], JSON_THROW_ON_ERROR);
        $attempts = [
            ['id' => 1, 'quiz_id' => 1, 'public_id' => $this->submittedAttemptId, 'name' => '=Alice Student', 'email' => 'alice@example.test', 'phone' => null, 'ip' => '203.0.113.7', 'agent' => 'Example Browser', 'status' => 'submitted', 'phase' => 'complete', 'settings' => $settings, 'started_at' => '2026-01-01 18:30:00', 'total_due_at' => null, 'close_at' => null, 'submitted_at' => '2026-01-01 18:35:00', 'finish_reason' => 'completed', 'score' => '1.60', 'max_score' => '2.00', 'percent' => '80.00', 'updated_at' => '2026-01-01 18:35:00'],
            ['id' => 2, 'quiz_id' => 1, 'public_id' => str_repeat('2', 32), 'name' => 'Bob Student', 'email' => null, 'phone' => '+998900000000', 'ip' => null, 'agent' => null, 'status' => 'expired', 'phase' => 'complete', 'settings' => $settings, 'started_at' => '2026-01-02 01:00:00', 'total_due_at' => null, 'close_at' => null, 'submitted_at' => '2026-01-02 01:10:00', 'finish_reason' => 'total_timeout', 'score' => '0.90', 'max_score' => '2.00', 'percent' => '45.00', 'updated_at' => '2026-01-02 01:10:00'],
            ['id' => 3, 'quiz_id' => 1, 'public_id' => str_repeat('3', 32), 'name' => 'Current Student', 'email' => null, 'phone' => null, 'ip' => null, 'agent' => null, 'status' => 'in_progress', 'phase' => 'answering', 'settings' => $settings, 'started_at' => '2026-01-03 00:00:00', 'total_due_at' => null, 'close_at' => null, 'submitted_at' => null, 'finish_reason' => null, 'score' => null, 'max_score' => '2.00', 'percent' => null, 'updated_at' => '2026-01-03 00:01:00'],
            ['id' => 4, 'quiz_id' => 4, 'public_id' => str_repeat('4', 32), 'name' => 'Archived Student', 'email' => null, 'phone' => null, 'ip' => null, 'agent' => null, 'status' => 'submitted', 'phase' => 'complete', 'settings' => $settings, 'started_at' => '2026-01-04 00:00:00', 'total_due_at' => null, 'close_at' => null, 'submitted_at' => '2026-01-04 00:05:00', 'finish_reason' => 'completed', 'score' => '1.80', 'max_score' => '2.00', 'percent' => '90.00', 'updated_at' => '2026-01-04 00:05:00'],
            ['id' => 5, 'quiz_id' => 5, 'public_id' => str_repeat('5', 32), 'name' => 'Deleted Student', 'email' => null, 'phone' => null, 'ip' => null, 'agent' => null, 'status' => 'submitted', 'phase' => 'complete', 'settings' => $settings, 'started_at' => '2026-01-05 00:00:00', 'total_due_at' => null, 'close_at' => null, 'submitted_at' => '2026-01-05 00:05:00', 'finish_reason' => 'completed', 'score' => '2.00', 'max_score' => '2.00', 'percent' => '100.00', 'updated_at' => '2026-01-05 00:05:00'],
            ['id' => 6, 'quiz_id' => 2, 'public_id' => str_repeat('6', 32), 'name' => 'Other Student', 'email' => null, 'phone' => null, 'ip' => null, 'agent' => null, 'status' => 'submitted', 'phase' => 'complete', 'settings' => $settings, 'started_at' => '2026-01-05 00:00:00', 'total_due_at' => null, 'close_at' => null, 'submitted_at' => '2026-01-05 00:05:00', 'finish_reason' => 'completed', 'score' => '2.00', 'max_score' => '2.00', 'percent' => '100.00', 'updated_at' => '2026-01-05 00:05:00'],
        ];
        foreach ($attempts as &$attempt) {
            $attempt['responses'] = \App\Services\Player\AttemptResponses::encode([]);
            $attempt['paper_id'] = match ((int) $attempt['quiz_id']) { 1 => 1, 4 => 2, 5 => 3, default => 4 };
        }
        unset($attempt);
        $this->reportingDb->table('attempts')->insertBatch($attempts);

        $this->reportingDb->table('questions')->insertBatch([
            ['id' => 1, 'quiz_id' => 1, 'pos' => 1, 'type' => 'single_choice', 'content' => 'Choose the correct answer.', 'media_type' => null, 'media_src' => null, 'explanation' => 'A clear explanation.', 'text_answers' => null],
            ['id' => 2, 'quiz_id' => 1, 'pos' => 2, 'type' => 'short_text', 'content' => 'Type the answer.', 'media_type' => null, 'media_src' => null, 'explanation' => null, 'text_answers' => json_encode(['accepted'], JSON_THROW_ON_ERROR)],
        ]);
        $this->reportingDb->table('question_options')->insertBatch([
            ['id' => 1, 'question_id' => 1, 'pos' => 1, 'code' => 'right', 'content' => 'Correct answer', 'media_type' => null, 'media_src' => null, 'is_correct' => 1],
            ['id' => 2, 'question_id' => 1, 'pos' => 2, 'code' => 'wrong', 'content' => 'Wrong answer', 'media_type' => null, 'media_src' => null, 'is_correct' => 0],
        ]);
        $responseRows = [
            ['id' => 1, 'attempt_id' => 1, 'quiz_id' => 1, 'question_id' => 1, 'pos' => 1, 'choice_order' => '["wrong","right"]', 'status' => 'locked', 'started_at' => '2026-01-01 18:30:00', 'locked_at' => '2026-01-01 18:30:30', 'lock_reason' => 'answered', 'answer_codes' => '["right"]', 'text_answer' => null, 'saved_at' => '2026-01-01 18:30:30', 'result' => 'correct', 'credit' => '1.00'],
            ['id' => 2, 'attempt_id' => 1, 'quiz_id' => 1, 'question_id' => 2, 'pos' => 2, 'choice_order' => '[]', 'status' => 'locked', 'started_at' => '2026-01-01 18:31:00', 'locked_at' => '2026-01-01 18:31:20', 'lock_reason' => 'answered', 'answer_codes' => null, 'text_answer' => 'other', 'saved_at' => '2026-01-01 18:31:20', 'result' => 'wrong', 'credit' => '0.00'],
            ['id' => 3, 'attempt_id' => 2, 'quiz_id' => 1, 'question_id' => 1, 'pos' => 1, 'choice_order' => '["right","wrong"]', 'status' => 'locked', 'started_at' => '2026-01-02 01:00:00', 'locked_at' => '2026-01-02 01:01:00', 'lock_reason' => 'answered', 'answer_codes' => '["wrong"]', 'text_answer' => null, 'saved_at' => '2026-01-02 01:01:00', 'result' => 'wrong', 'credit' => '0.00'],
            ['id' => 4, 'attempt_id' => 3, 'quiz_id' => 1, 'question_id' => 1, 'pos' => 1, 'choice_order' => '["right","wrong"]', 'status' => 'active', 'started_at' => '2026-01-03 00:00:00', 'locked_at' => null, 'lock_reason' => null, 'answer_codes' => '["wrong"]', 'text_answer' => null, 'saved_at' => '2026-01-03 00:00:30', 'result' => null, 'credit' => null],
        ];
        $grouped = [];
        foreach ($responseRows as $row) {
            $row['save_ver'] = 1;
            if ($row['status'] === 'locked') {
                $row['submit_key'] = md5((string) $row['id']);
                $row['submit_hash'] = hash('sha256', (string) $row['id']);
            }
            $grouped[$row['attempt_id']][] = $row;
        }
        foreach ($grouped as $attemptId => $items) {
            $this->reportingDb->table('attempts')->where('id', $attemptId)->update([
                'responses' => \App\Services\Player\AttemptResponses::encode($items),
            ]);
        }
        $this->reportingDb->table('cheat_events')->insertBatch([
            ['id' => 1, 'attempt_id' => 1, 'type' => 'tab_hidden', 'happened_at' => '2026-01-01 18:32:00', 'received_at' => '2026-01-01 18:32:01', 'duration_ms' => null, 'data' => '{"visibilityState":"hidden","tokenHash":"secret"}'],
            ['id' => 2, 'attempt_id' => 1, 'type' => 'fullscreen_exit', 'happened_at' => '2026-01-01 18:32:02', 'received_at' => '2026-01-01 18:32:02', 'duration_ms' => null, 'data' => '{}'],
            ['id' => 3, 'attempt_id' => 6, 'type' => 'fullscreen_exit', 'happened_at' => null, 'received_at' => '2026-01-05 00:01:00', 'duration_ms' => null, 'data' => '{}'],
        ]);
    }

    private function createTables(): void
    {
        $p = fn (string $table): string => $this->reportingDb->prefixTable($table);
        $this->reportingDb->query("CREATE TABLE {$p('quizzes')} (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, public_id TEXT NOT NULL UNIQUE, mode TEXT NOT NULL, status TEXT NOT NULL, title TEXT NOT NULL, deleted_at TEXT NULL, updated_at TEXT NOT NULL)");
        $this->reportingDb->query("CREATE TABLE {$p('quiz_papers')} (id INTEGER PRIMARY KEY, quiz_id INTEGER NOT NULL, public_id TEXT NOT NULL UNIQUE, revision INTEGER NOT NULL, definition TEXT NOT NULL, created_at TEXT NOT NULL)");
        $this->reportingDb->query("CREATE TABLE {$p('attempts')} (id INTEGER PRIMARY KEY, quiz_id INTEGER NOT NULL, paper_id INTEGER NOT NULL, public_id TEXT NOT NULL UNIQUE, name TEXT NOT NULL, email TEXT NULL, phone TEXT NULL, ip TEXT NULL, agent TEXT NULL, status TEXT NOT NULL, phase TEXT NOT NULL, settings TEXT NOT NULL, responses TEXT NOT NULL, started_at TEXT NOT NULL, total_due_at TEXT NULL, close_at TEXT NULL, due_at TEXT NULL, submitted_at TEXT NULL, finish_reason TEXT NULL, score NUMERIC NULL, max_score NUMERIC NOT NULL, percent NUMERIC NULL, updated_at TEXT NOT NULL)");
        $this->reportingDb->query("CREATE TABLE {$p('questions')} (id INTEGER PRIMARY KEY, quiz_id INTEGER NOT NULL, pos INTEGER NOT NULL, type TEXT NOT NULL, content TEXT NOT NULL, media_type TEXT NULL, media_src TEXT NULL, explanation TEXT NULL, text_answers TEXT NULL)");
        $this->reportingDb->query("CREATE TABLE {$p('question_options')} (id INTEGER PRIMARY KEY, question_id INTEGER NOT NULL, pos INTEGER NOT NULL, code TEXT NOT NULL, content TEXT NOT NULL, media_type TEXT NULL, media_src TEXT NULL, is_correct INTEGER NOT NULL)");
        $this->reportingDb->query("CREATE TABLE {$p('cheat_events')} (id INTEGER PRIMARY KEY, attempt_id INTEGER NOT NULL, type TEXT NOT NULL, happened_at TEXT NULL, received_at TEXT NOT NULL, duration_ms INTEGER NULL, data TEXT NOT NULL)");
    }

    private function dropTables(): void
    {
        foreach (['cheat_events', 'question_options', 'questions', 'attempts', 'quiz_papers', 'quizzes'] as $table) {
            $this->reportingDb->query('DROP TABLE IF EXISTS ' . $this->reportingDb->prefixTable($table));
        }
    }
}
