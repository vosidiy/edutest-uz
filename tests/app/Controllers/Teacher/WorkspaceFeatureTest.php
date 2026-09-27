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

    public function testDashboardSummariesIncludeHistoryWithoutLoadingAnswerDocuments(): void
    {
        $quiz = $this->quiz();
        $first = $this->startQuiz($quiz);
        $this->db->table('attempts')->where('public_id', $first['attemptId'])->update([
            'status' => 'submitted', 'score' => '0.00', 'percent' => '0.00',
            'submitted_at' => '2026-01-01 21:30:00', 'responses' => '{"not":"decoded by summaries"}',
        ]);
        $this->startQuiz($quiz);
        $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(['mode' => 'practice', 'email_mode' => 'hidden', 'practice_starts' => 12]);
        $other = $this->quiz();
        $this->startQuiz($other);

        $dashboard = $this->queries->dashboard($quiz['owner'], [], 'Asia/Tashkent');
        $this->assertSame(['totalQuizzes' => 1, 'publishedQuizzes' => 1, 'assessmentSubmissions' => 1, 'inProgressAttempts' => 1, 'averagePercent' => '0.00', 'practiceStarts' => 12], $dashboard['metrics']);
        $row = $dashboard['library']['rows'][0];
        $this->assertSame(2, $row['questionCount']);
        $this->assertSame(1, $row['assessmentSubmissions']);
        $this->assertSame(1, $row['inProgressAttempts']);
        $this->assertSame('02 Jan 2026, 02:30', $row['latestSubmission']);
        $this->assertNotNull($row['resultsUrl']);
        $this->assertArrayNotHasKey('responses', $row);
        $this->assertArrayNotHasKey('id', $row);

        $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(['status' => 'archived', 'deleted_at' => '2026-01-02 00:00:00']);
        $historical = $this->queries->dashboard($quiz['owner'], ['view' => 'all']);
        $this->assertSame(1, $historical['metrics']['assessmentSubmissions']);
        $this->assertSame(0, $historical['metrics']['practiceStarts']);
        $this->assertNull($historical['library']['rows'][0]['editUrl']);
        $this->assertTrue($historical['library']['rows'][0]['deleted']);
    }

    public function testSortingAndAverageUseAttemptCountsNotAverageOfQuizAverages(): void
    {
        $low = $this->quiz();
        $high = $this->quiz();
        $this->db->table('quizzes')->where('public_id', $high['publicId'])->update(['user_id' => $low['owner'], 'title' => 'High scoring quiz']);
        foreach ([[$low, '0.00'], [$high, '80.00'], [$high, '80.00']] as [$quiz, $percent]) {
            $attempt = $this->startQuiz($quiz);
            $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update(['status' => 'submitted', 'percent' => $percent]);
        }
        $this->assertSame('53.33', $this->queries->dashboard($low['owner'])['metrics']['averagePercent']);
        foreach (['submissions_desc', 'score_desc'] as $sort) {
            $rows = $this->queries->library($low['owner'], ['sort' => $sort])['rows'];
            $this->assertSame($high['publicId'], $rows[0]['publicId']);
            $this->assertSame(2, $rows[0]['assessmentSubmissions']);
            $this->assertSame('80.00', $rows[0]['averagePercent']);
        }
    }

    public function testLegacyFiltersAreTranslatedAndUnrecognizedInputsAreIgnored(): void
    {
        $url = $this->queries->legacyDashboardUrl(['lifecycle' => 'deleted', 'q' => 'Algebra', 'sort' => 'score_desc', 'page' => 3, 'redirect' => 'https://foreign.invalid'], 'results');
        parse_str(parse_url($url, PHP_URL_QUERY), $filters);
        $this->assertSame(['q' => 'Algebra', 'view' => 'trash', 'reports' => '1', 'sort' => 'score_desc', 'page' => '3'], $filters);
        $quiz = $this->quiz();
        $dashboard = $this->queries->dashboard($quiz['owner'] + 100);
        $this->assertSame(0, $dashboard['metrics']['totalQuizzes']);
        $this->assertSame(0, $dashboard['metrics']['assessmentSubmissions']);
        $this->assertNull($dashboard['metrics']['averagePercent']);
        $this->assertSame([], $dashboard['library']['rows']);
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
        $this->assertSame(2, $this->queries->library($owner, [])['pagination']['total']);
        $this->assertSame(4, $this->queries->library($owner, [], 'all')['pagination']['total']);
        $this->assertSame(1, $this->queries->library($owner, [], 'archived')['pagination']['total']);
        $this->assertSame(1, $this->queries->library($owner, [], 'trash')['pagination']['total']);
        $this->assertSame(3, $this->queries->library($owner, ['reports' => '1'], 'all')['pagination']['total']);
        $practice = $this->queries->library($owner, ['q' => 'Anonymous', 'mode' => 'practice', 'status' => 'draft']);
        $this->assertCount(1, $practice['rows']);
        $this->assertNull($practice['rows'][0]['resultsUrl']);
        $this->assertNull($practice['rows'][0]['averagePercent']);
        $this->assertSame([], $this->queries->library($owner, ['q' => 'missing'])['rows']);
        foreach (['updated_desc', 'created_desc', 'title_asc', 'submissions_desc', 'score_desc'] as $sort) {
            $this->assertCount(4, $this->queries->library($owner, ['sort' => $sort], 'all')['rows']);
        }
        for ($i = 0; $i < 21; $i++) $this->authoring->create($owner, sprintf('Page %02d', $i), 'assessment');
        $page = $this->queries->library($owner, ['q' => 'Page', 'sort' => 'title_asc', 'page' => 999]);
        $this->assertSame(2, $page['pagination']['page']);
        $this->assertCount(1, $page['rows']);
        $this->assertSame('Page 20', $page['rows'][0]['title']);
        $this->assertSame('updated_desc', $this->queries->normalizeFilters(['sort' => 'DROP TABLE quizzes'])['sort']);
        $this->assertSame('', $this->queries->normalizeFilters(['q' => ['bad']])['q']);
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
        $response->assertSee('create-quiz-dialog');
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
