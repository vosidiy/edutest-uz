<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Teacher;

use App\Services\AuthService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Services;

final class ResultsFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $reportDb;
    private string $quizPublicId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportDb = Database::connect('tests');
        $this->dropTables();
        $this->createTables();
        $this->seed();
        service('session')->destroy();
        $_SESSION = [];
        Services::resetSingle('auth');
        Services::resetSingle('media');
        Services::resetSingle('quizPapers');
        Services::resetSingle('teacherResults');
    }

    protected function tearDown(): void
    {
        Services::resetSingle('teacherResults');
        Services::resetSingle('media');
        Services::resetSingle('quizPapers');
        Services::resetSingle('auth');
        service('session')->destroy();
        $_SESSION = [];
        $this->dropTables();
        parent::tearDown();
    }

    public function testAuthenticatedOwnerCanRenderOverviewAndQuizReport(): void
    {
        $session = [AuthService::SESSION_KEY => 1];
        $overview = $this->withSession($session)->get('/results');

        $overview->assertRedirectTo(service('teacherQueries')->legacyDashboardUrl([], 'results'));

        $quiz = $this->withSession($session)->get('/results/quizzes/' . $this->quizPublicId);
        $quiz->assertStatus(200);
        $quiz->assertSee('=SUM(1,1)');
        $quiz->assertDontSee('Score distribution');
        $quiz->assertSee('Paper revision 3');
        $quiz->assertSee('Review');
        $quiz->assertSee('builder-bar');
        $quiz->assertSee('aria-current="page"');
        $quiz->assertDontSee('teacher-sidebar');
        $quiz->assertDontSee('teacher-topbar');
        $this->assertStringContainsString('private', $quiz->response()->getHeaderLine('Cache-Control'));
    }

    public function testCsvDownloadProtectsFormulaCellsAndExcludesConnectionDetails(): void
    {
        $result = $this->withSession([AuthService::SESSION_KEY => 1])
            ->get('/results/quizzes/' . $this->quizPublicId . '/export.csv');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $body = $result->response()->getBody();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString("'=SUM(1,1)", $body);
        $this->assertStringContainsString("'+998900000000", $body);
        $this->assertStringNotContainsString('203.0.113.50', $body);
        $this->assertStringNotContainsString('Sensitive Browser Agent', $body);
        $this->assertStringNotContainsString('token_hash', $body);
    }

    public function testNonOwnedAndMalformedQuizIdentifiersReturnTheSameNotFoundBoundary(): void
    {
        foreach ([str_repeat('b', 32), 'malformed'] as $publicId) {
            try {
                $this->withSession([AuthService::SESSION_KEY => 1])->get('/results/quizzes/' . $publicId);
                $this->fail('A non-owned or malformed report should not render.');
            } catch (PageNotFoundException $exception) {
                $this->assertSame(404, $exception->getCode());
            }
        }
    }

    private function seed(): void
    {
        $now = '2026-01-01 00:00:00';
        $this->quizPublicId = str_repeat('a', 32);
        $this->reportDb->table('users')->insertBatch([
            ['id' => 1, 'email' => 'owner@example.test', 'password_hash' => password_hash('secret1', PASSWORD_DEFAULT), 'display_name' => 'Owner Teacher', 'phone' => null, 'bio' => '', 'timezone' => 'Asia/Tashkent', 'public_page' => 0, 'active' => 1, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['id' => 2, 'email' => 'other@example.test', 'password_hash' => password_hash('secret1', PASSWORD_DEFAULT), 'display_name' => 'Other Teacher', 'phone' => null, 'bio' => '', 'timezone' => 'UTC', 'public_page' => 0, 'active' => 1, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        ]);
        $this->reportDb->table('quizzes')->insertBatch([
            ['id' => 1, 'user_id' => 1, 'public_id' => $this->quizPublicId, 'mode' => 'assessment', 'status' => 'published', 'title' => 'Owner assessment', 'deleted_at' => null, 'updated_at' => $now],
            ['id' => 2, 'user_id' => 2, 'public_id' => str_repeat('b', 32), 'mode' => 'assessment', 'status' => 'published', 'title' => 'Other assessment', 'deleted_at' => null, 'updated_at' => $now],
        ]);
        $this->reportDb->table('quiz_papers')->insert([
            'id' => 1, 'quiz_id' => 1, 'public_id' => str_repeat('c', 32),
            'revision' => 3, 'definition' => '{"schemaVersion":2,"quiz":{},"questions":[]}', 'created_at' => $now,
        ]);
        $this->reportDb->table('attempts')->insert([
            'id' => 1,
            'quiz_id' => 1,
            'paper_id' => 1,
            'public_id' => str_repeat('1', 32),
            'name' => '=SUM(1,1)',
            'email' => 'student@example.test',
            'phone' => '+998900000000',
            'ip' => '203.0.113.50',
            'agent' => 'Sensitive Browser Agent',
            'status' => 'submitted',
            'phase' => 'complete',
            'settings' => '{}',
            'responses' => '{"schemaVersion":1,"items":[]}',
            'started_at' => '2026-01-01 00:00:00',
            'total_due_at' => null,
            'close_at' => null,
            'due_at' => null,
            'submitted_at' => '2026-01-01 00:05:00',
            'finish_reason' => 'completed',
            'score' => '0.70',
            'max_score' => '1.00',
            'percent' => '70.00',
            'updated_at' => '2026-01-01 00:05:00',
        ]);
    }

    private function createTables(): void
    {
        $p = fn (string $table): string => $this->reportDb->prefixTable($table);
        $queries = [
            "CREATE TABLE {$p('users')} (id INTEGER PRIMARY KEY, email TEXT NOT NULL, password_hash TEXT NOT NULL, display_name TEXT NOT NULL, phone TEXT NULL, bio TEXT NOT NULL, timezone TEXT NOT NULL, public_page INTEGER NOT NULL, active INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, deleted_at TEXT NULL)",
            "CREATE TABLE {$p('quizzes')} (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, public_id TEXT NOT NULL UNIQUE, mode TEXT NOT NULL, status TEXT NOT NULL, title TEXT NOT NULL, deleted_at TEXT NULL, updated_at TEXT NOT NULL)",
            "CREATE TABLE {$p('quiz_papers')} (id INTEGER PRIMARY KEY, quiz_id INTEGER NOT NULL, public_id TEXT NOT NULL UNIQUE, revision INTEGER NOT NULL, definition TEXT NOT NULL, created_at TEXT NOT NULL)",
            "CREATE TABLE {$p('attempts')} (id INTEGER PRIMARY KEY, quiz_id INTEGER NOT NULL, paper_id INTEGER NOT NULL, public_id TEXT NOT NULL UNIQUE, name TEXT NOT NULL, email TEXT NULL, phone TEXT NULL, ip TEXT NULL, agent TEXT NULL, status TEXT NOT NULL, phase TEXT NOT NULL, settings TEXT NOT NULL, responses TEXT NOT NULL, started_at TEXT NOT NULL, total_due_at TEXT NULL, close_at TEXT NULL, due_at TEXT NULL, submitted_at TEXT NULL, finish_reason TEXT NULL, score NUMERIC NULL, max_score NUMERIC NOT NULL, percent NUMERIC NULL, updated_at TEXT NOT NULL)",
            "CREATE TABLE {$p('questions')} (id INTEGER PRIMARY KEY, quiz_id INTEGER NOT NULL, pos INTEGER NOT NULL, type TEXT NOT NULL, content TEXT NOT NULL, media_type TEXT NULL, media_src TEXT NULL, explanation TEXT NULL, text_answers TEXT NULL)",
            "CREATE TABLE {$p('question_options')} (id INTEGER PRIMARY KEY, question_id INTEGER NOT NULL, pos INTEGER NOT NULL, code TEXT NOT NULL, content TEXT NOT NULL, media_type TEXT NULL, media_src TEXT NULL, is_correct INTEGER NOT NULL)",
            "CREATE TABLE {$p('cheat_events')} (id INTEGER PRIMARY KEY, attempt_id INTEGER NOT NULL, type TEXT NOT NULL, happened_at TEXT NULL, received_at TEXT NOT NULL, duration_ms INTEGER NULL, data TEXT NOT NULL)",
        ];
        foreach ($queries as $query) {
            $this->reportDb->query($query);
        }
    }

    private function dropTables(): void
    {
        foreach (['cheat_events', 'question_options', 'questions', 'attempts', 'quiz_papers', 'quizzes', 'users'] as $table) {
            $this->reportDb->query('DROP TABLE IF EXISTS ' . $this->reportDb->prefixTable($table));
        }
    }
}
