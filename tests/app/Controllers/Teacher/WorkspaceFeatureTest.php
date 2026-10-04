<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Teacher;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\QuizPaperService;
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
        $this->queries = new TeacherQueryService($this->db);
        Services::injectMock('teacherQueries', $this->queries);
        Services::injectMock('quizAuthoring', $this->authoring);
        Services::injectMock('teacherResults', new TeacherResultsService($this->db, $this->media, new QuizPaperService($this->db, $this->media)));
        Services::injectMock('auth', new AuthService(new UserModel($this->db), service('session')));
    }

    protected function tearDown(): void
    {
        foreach (['teacherQueries', 'quizAuthoring', 'teacherResults', 'auth'] as $name) Services::resetSingle($name);
        service('session')->destroy();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testQuizCardsExposeOnlyMvpActivityWithoutLoadingAnswerDocuments(): void
    {
        $quiz = $this->quiz();
        $first = $this->startQuiz($quiz);
        $this->db->table('attempts')->where('public_id', $first['attemptId'])->update([
            'status' => 'completed', 'score' => 0, 'percent' => '0.00',
            'finished_at' => '2026-01-01 21:30:00', 'ended_reason' => 'completed',
        ]);
        $this->startQuiz($quiz);
        $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(['mode' => 'practice', 'email_mode' => 'hidden', 'practice_starts' => 12]);
        $other = $this->quiz();
        $this->startQuiz($other);

        $library = $this->queries->library($quiz['owner'], [], 'Asia/Tashkent');
        $row = $library['rows'][0];
        $this->assertSame(2, $row['questionCount']);
        $this->assertSame(1, $row['assessmentSubmissions']);
        $this->assertSame(12, $row['practiceStarts']);
        $this->assertNotNull($row['resultsUrl']);
        $this->assertArrayNotHasKey('inProgressAttempts', $row);
        $this->assertArrayNotHasKey('averagePercent', $row);
        $this->assertArrayNotHasKey('latestSubmission', $row);
        $this->assertArrayNotHasKey('responses', $row);
        $this->assertArrayNotHasKey('id', $row);

        $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(['status' => 'archived', 'deleted_at' => '2026-01-02 00:00:00']);
        $this->assertSame([], $this->queries->library($quiz['owner'])['rows']);
        $trashed = $this->queries->library($quiz['owner'], ['status' => 'trash']);
        $this->assertNull($trashed['rows'][0]['editUrl']);
        $this->assertTrue($trashed['rows'][0]['deleted']);
    }

    public function testFinalizedAttemptSortingUsesFinalizedCounts(): void
    {
        $low = $this->quiz();
        $high = $this->quiz();
        $this->db->table('quizzes')->where('public_id', $high['publicId'])->update(['user_id' => $low['owner'], 'title' => 'High scoring quiz']);
        foreach ([[$low, '0.00'], [$high, '80.00'], [$high, '80.00']] as [$quiz, $percent]) {
            $attempt = $this->startQuiz($quiz);
            $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update([
                'status' => 'completed', 'finished_at' => '2026-01-01 01:00:00', 'ended_reason' => 'completed',
                'score' => (int) round((float) $percent * 2 / 100), 'percent' => $percent,
            ]);
        }
        $rows = $this->queries->library($low['owner'], ['sort' => 'submissions_desc'])['rows'];
        $this->assertSame($high['publicId'], $rows[0]['publicId']);
        $this->assertSame(2, $rows[0]['assessmentSubmissions']);
        $this->assertArrayNotHasKey('averagePercent', $rows[0]);
    }

    public function testLegacyFiltersAreTranslatedAndUnrecognizedInputsAreIgnored(): void
    {
        $url = $this->queries->legacyDashboardUrl(['lifecycle' => 'deleted', 'q' => 'Algebra', 'sort' => 'score_desc', 'page' => 3, 'redirect' => 'https://foreign.invalid'], 'results');
        parse_str(parse_url($url, PHP_URL_QUERY), $filters);
        $this->assertSame(['status' => 'trash', 'sort' => 'updated_desc', 'page' => '3'], $filters);
        $quiz = $this->quiz();
        $this->assertSame([], $this->queries->library($quiz['owner'] + 100)['rows']);
    }

    public function testFiltersSortsPaginationAndEmptyStates(): void
    {
        $quiz = $this->quiz();
        $owner = $quiz['owner'];
        $this->authoring->create($owner, 'Anonymous practice', 'practice');
        $archived = $this->authoring->create($owner, 'Archived assessment', 'assessment');
        $this->authoring->transition($owner, $archived['publicId'], 'archive');
        $trashed = $this->authoring->create($owner, 'Trashed assessment', 'assessment');
        $this->authoring->transition($owner, $trashed['publicId'], 'trash');
        $this->assertSame(3, $this->queries->library($owner)['pagination']['total']);
        $this->assertSame(1, $this->queries->library($owner, ['status' => 'archived'])['pagination']['total']);
        $this->assertSame(1, $this->queries->library($owner, ['status' => 'trash'])['pagination']['total']);
        $practice = $this->queries->library($owner, ['mode' => 'practice', 'status' => 'draft']);
        $this->assertCount(1, $practice['rows']);
        $this->assertNull($practice['rows'][0]['resultsUrl']);
        $this->assertArrayNotHasKey('averagePercent', $practice['rows'][0]);
        foreach (['updated_desc', 'created_desc', 'title_asc', 'submissions_desc'] as $sort) {
            $this->assertCount(3, $this->queries->library($owner, ['sort' => $sort])['rows']);
        }
        for ($i = 0; $i < 21; $i++) $this->authoring->create($owner, sprintf('Page %02d', $i), 'assessment');
        $page = $this->queries->library($owner, ['sort' => 'title_asc', 'page' => 999]);
        $this->assertSame(2, $page['pagination']['page']);
        $this->assertCount(4, $page['rows']);
        $this->assertSame('updated_desc', $this->queries->normalizeFilters(['sort' => 'DROP TABLE quizzes'])['sort']);
        $this->assertSame(['status', 'mode', 'sort', 'page'], array_keys($this->queries->normalizeFilters(['q' => 'ignored', 'reports' => '1', 'view' => 'trash'])));
    }

    public function testDashboardAndCompatibilityRedirectsAreOwnerScoped(): void
    {
        $quiz = $this->quiz();
        $this->authoring->create($quiz['owner'], '<script>unsafe title</script>', 'assessment');
        $other = $this->quiz();
        $this->db->table('quizzes')->where('public_id', $other['publicId'])->update(['title' => 'Other private quiz']);
        $session = [AuthService::SESSION_KEY => $quiz['owner']];
        $response = $this->withSession($session)->get('/dashboard');
        $response->assertOK();
        $response->assertSee('teacher-topbar');
        $response->assertSee('data-account-menu');
        $response->assertSee('class="dropdown"');
        $response->assertSee('class="dropdown-item"');
        $response->assertSee('href="' . site_url('account') . '"');
        $response->assertDontSee('account-popover');
        $response->assertSee('create-quiz-dialog');
        $response->assertSee('class="card quiz-card"');
        $response->assertSee('class="quiz-card-media"');
        $response->assertSee('class="quiz-card-media" href="' . site_url('quizzes/' . $quiz['publicId'] . '/edit') . '"');
        $response->assertSee('class="quiz-card-actions"');
        $response->assertSee('class="dropdown"');
        $response->assertDontSee('dashboard-metrics');
        $response->assertDontSee('library-tabs');
        $response->assertDontSee('name="q"');
        $response->assertDontSee('name="reports"');
        $response->assertDontSee('class="menu"');
        $response->assertDontSee('teacher-sidebar');
        $response->assertDontSee('Other private quiz');
        $this->assertStringContainsString('&lt;script&gt;unsafe title&lt;/script&gt;', $response->response()->getBody());
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
        foreach (['/quizzes' => 'active', '/quizzes/archived' => 'archived', '/quizzes/trash' => 'trash', '/results' => 'results'] as $url => $source) {
            $input = ['q' => 'Capital', 'sort' => 'title_asc', 'page' => '2', 'lifecycle' => 'deleted'];
            $this->withSession($session)->get($url . '?' . http_build_query($input))
                ->assertRedirectTo($this->queries->legacyDashboardUrl($input, $source));
        }
    }

    public function testQuizHeadersAreSharedAndHistoricalReportsKeepUnavailableBuilder(): void
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        $session = [AuthService::SESSION_KEY => $quiz['owner']];
        foreach (['/quizzes/' . $quiz['publicId'] . '/edit', '/results/quizzes/' . $quiz['publicId'], '/results/attempts/' . $attempt['attemptId']] as $url) {
            $response = $this->withSession($session)->get($url);
            $response->assertOK();
            $response->assertSee('builder-bar');
            $response->assertSee('Quiz builder');
            $response->assertSee('Responses');
            $response->assertDontSee('teacher-topbar');
            $response->assertDontSee('teacher-sidebar');
        }
        $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(['status' => 'archived']);
        foreach ([null, '2026-01-02 00:00:00'] as $deleted) {
            $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(['deleted_at' => $deleted]);
            $response = $this->withSession($session)->get('/results/quizzes/' . $quiz['publicId']);
            $response->assertOK();
            $response->assertSee('builder-unavailable');
            $response->assertSee('aria-disabled="true"');
            $response->assertDontSee('/quizzes/' . $quiz['publicId'] . '/edit');
            $this->withSession($session)->get('/results/quizzes/' . $quiz['publicId'] . '/export.csv')->assertOK();
        }
    }
}
