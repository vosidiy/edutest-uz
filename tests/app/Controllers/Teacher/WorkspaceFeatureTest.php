<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Teacher;

use App\Models\UserModel;
use App\Services\AttemptSnapshotService;
use App\Services\AuthService;
use App\Services\TeacherQueryService;
use App\Services\TeacherResultsService;
use CodeIgniter\Exceptions\PageNotFoundException;
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
        Services::injectMock('media', $this->media);
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
        foreach (['teacherQueries', 'quizAuthoring', 'media', 'teacherResults', 'auth'] as $name) {
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

    public function testLibraryIgnoresOldFiltersHidesDeletedAndUsesNewestUpdateOrder(): void
    {
        $quiz = $this->quiz();
        $draft = $this->authoring->create($quiz['owner'], 'Draft quiz');
        $trash = $this->authoring->create($quiz['owner'], 'Trash quiz');
        $this->authoring->transition($quiz['owner'], $trash['publicId'], 'trash');
        $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update([
            'title' => 'Older published quiz', 'updated_at' => '2026-10-26 00:00:00.000000',
        ]);
        $this->db->table('quizzes')->where('public_id', $draft['publicId'])->update([
            'title' => 'Newest draft quiz', 'updated_at' => '2026-10-27 00:00:00.000000',
        ]);

        $library = $this->queries->library($quiz['owner'], [
            'status' => 'trash', 'sort' => 'title_asc', 'page' => '1',
        ]);
        self::assertSame(2, $library['pagination']['total']);
        self::assertSame(['page' => 1], $library['filters']);
        self::assertSame(['Newest draft quiz', 'Older published quiz'], array_column($library['rows'], 'title'));
        self::assertNotContains('Trash quiz', array_column($library['rows'], 'title'));
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
        $response->assertDontSee('class="library-toolbar"');
        self::assertStringNotContainsString('data-quiz-action="publish"', $response->getBody());
        self::assertStringNotContainsString('data-quiz-action="unpublish"', $response->getBody());
        foreach (['name="status"', 'name="sort"', 'Sort order', 'All statuses'] as $removed) {
            $response->assertDontSee($removed);
        }
        self::assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));

        $this->withSession($session)->get('/quizzes?status=trash&sort=title_asc')->assertRedirectTo(site_url('dashboard'));
        try {
            $this->withSession($session)->get('/quizzes/trash');
            self::fail('The removed trash route must not resolve.');
        } catch (PageNotFoundException $exception) {
            self::assertSame(404, $exception->getCode());
        }
    }

    public function testBuilderShowsManualSaveAndStatusSwitchWithoutLegacyControls(): void
    {
        $quiz = $this->quiz();
        $response = $this->withSession([AuthService::SESSION_KEY => $quiz['owner']])
            ->get('/quizzes/' . $quiz['publicId'] . '/edit');

        $response->assertOK();
        $response->assertSee('Save quiz');
        $response->assertSee('data-save-quiz');
        self::assertStringContainsString('role="switch"', $response->getBody());
        $response->assertSee('Active');
        $response->assertSee('Draft');
        $response->assertSee('Total timer (minutes)');
        $response->assertSee('Questions per attempt');
        $response->assertSee('List');
        $response->assertSee('Grid');
        self::assertStringNotContainsString('>Publish</button>', $response->getBody());
        self::assertStringNotContainsString('>Unpublish</button>', $response->getBody());
        $response->assertDontSee('Fix validation errors');
        $response->assertDontSee('Video URL');
        $response->assertDontSee('supported video');
        $response->assertDontSee('preview = true');
        $response->assertDontSee('preview = false');
        $response->assertDontSee('preview-paper');
        $response->assertDontSee('preview-options');
        $response->assertDontSee('Show on my page');
        $response->assertDontSee('Newer changes found');
        $response->assertDontSee('Saved changes apply to students');
        $response->assertDontSee('data-publication-messages');
        $response->assertDontSee('Practice');
        $response->assertDontSee('Passcode');
        $response->assertDontSee('Short text');
    }

    public function testQuestionMediaEndpointRejectsLegacyVideoJson(): void
    {
        $quiz = $this->quiz(count: 1);
        $questionId = $quiz['document']['questions'][0]['id'];
        $csrf = service('security')->generateHash();
        $response = $this->withSession([
            AuthService::SESSION_KEY => $quiz['owner'],
            'csrf_test_name' => $csrf,
        ])
            ->withHeaders(['X-CSRF-TOKEN' => $csrf])
            ->withBodyFormat('json')
            ->post('/api/v1/quizzes/' . $quiz['publicId'] . '/questions/' . $questionId . '/media', [
                'videoUrl' => 'https://example.test/legacy.mp4',
            ]);

        $response->assertStatus(422);
        $response->assertSee('invalid_upload');
    }
}
