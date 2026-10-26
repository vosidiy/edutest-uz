<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Teacher;

use App\Models\UserModel;
use App\Services\AttemptSnapshotService;
use App\Services\AuthService;
use App\Services\TeacherQueryService;
use App\Services\TeacherResultsService;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\PlayerTestCase;

final class WorkspaceFeatureTest extends PlayerTestCase
{
    use FeatureTestTrait;

    private TeacherQueryService $queries;

    protected function setUp(): void
    {
        parent::setUp();
        service('session')->destroy();
        $_SESSION = [];
        $this->queries = new TeacherQueryService($this->db, $this->media);
        Services::injectMock('teacherQueries', $this->queries);
        Services::injectMock('quizAuthoring', $this->authoring);
        Services::injectMock('teacherResults', new TeacherResultsService(
            $this->db,
            $this->media,
            new AttemptSnapshotService($this->db, $this->media),
            $this->player,
        ));
        Services::injectMock('auth', new AuthService(new UserModel($this->db), service('session')));
    }

    protected function tearDown(): void
    {
        foreach (['teacherQueries', 'quizAuthoring', 'teacherResults', 'auth'] as $name) {
            Services::resetSingle($name);
        }
        service('session')->destroy();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testLibraryIsAssessmentOnlyAndCountsFinalizedAttempts(): void
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        $answers = [$this->submission($attempt, 0), $this->submission($attempt, 1)];
        $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['credential'],
            $this->finishBody($attempt, $answers, 2),
        );

        $row = $this->queries->library($quiz['owner'])['rows'][0];
        self::assertSame(2, $row['questionCount']);
        self::assertSame(1, $row['assessmentSubmissions']);
        self::assertNotNull($row['resultsUrl']);
        self::assertArrayNotHasKey('mode', $row);
        self::assertArrayNotHasKey('practiceStarts', $row);
    }

    public function testFiltersOnlyAcceptDraftPublishedAndTrash(): void
    {
        $quiz = $this->quiz();
        $draft = $this->authoring->create($quiz['owner'], 'Draft quiz');
        $trash = $this->authoring->create($quiz['owner'], 'Trash quiz');
        $this->authoring->transition($quiz['owner'], $trash['publicId'], 'trash');

        self::assertSame(2, $this->queries->library($quiz['owner'])['pagination']['total']);
        self::assertSame(1, $this->queries->library($quiz['owner'], ['status' => 'published'])['pagination']['total']);
        self::assertSame(1, $this->queries->library($quiz['owner'], ['status' => 'draft'])['pagination']['total']);
        self::assertSame(1, $this->queries->library($quiz['owner'], ['status' => 'trash'])['pagination']['total']);
        self::assertSame(['status', 'sort', 'page'], array_keys($this->queries->normalizeFilters(['mode' => 'practice', 'status' => 'archived'])));
        self::assertSame('', $this->queries->normalizeFilters(['status' => 'archived'])['status']);
        self::assertNotEmpty($draft['publicId']);
    }

    public function testDashboardAndLegacyRoutesAreOwnerScoped(): void
    {
        $quiz = $this->quiz();
        $other = $this->quiz();
        $this->db->table('quizzes')->where('public_id', $other['publicId'])->update(['title' => 'Other private quiz']);
        $session = [AuthService::SESSION_KEY => $quiz['owner']];

        $response = $this->withSession($session)->get('/dashboard');
        $response->assertOK();
        $response->assertSee('Create a quiz');
        $response->assertSee('class="card quiz-card"');
        $response->assertDontSee('Other private quiz');
        $response->assertDontSee('Practice');
        self::assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));

        $this->withSession($session)->get('/quizzes')->assertRedirectTo($this->queries->legacyDashboardUrl([], 'active'));
        $this->withSession($session)->get('/quizzes/trash')->assertRedirectTo($this->queries->legacyDashboardUrl([], 'trash'));
    }

    public function testBuilderShowsPublishOrUnpublishWithoutLegacyControls(): void
    {
        $quiz = $this->quiz();
        $response = $this->withSession([AuthService::SESSION_KEY => $quiz['owner']])
            ->get('/quizzes/' . $quiz['publicId'] . '/edit');

        $response->assertOK();
        $response->assertSee('Unpublish');
        $response->assertSee('Questions per attempt');
        $response->assertSee('List');
        $response->assertSee('Grid');
        $response->assertDontSee('Practice');
        $response->assertDontSee('Passcode');
        $response->assertDontSee('Short text');
    }
}
