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

final class ResultsFeatureTest extends PlayerTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        service('session')->destroy();
        $_SESSION = [];
        Services::injectMock('auth', new AuthService(new UserModel($this->db), service('session')));
        Services::injectMock('teacherQueries', new TeacherQueryService($this->db, $this->media));
        Services::injectMock('teacherResults', new TeacherResultsService(
            $this->db,
            $this->media,
            new AttemptSnapshotService($this->db, $this->media),
            $this->player,
        ));
    }

    protected function tearDown(): void
    {
        foreach (['teacherResults', 'teacherQueries', 'auth'] as $name) {
            Services::resetSingle($name);
        }
        service('session')->destroy();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testOwnerCanRenderSnapshotBackedReportAndReview(): void
    {
        [$quiz, $attempt] = $this->completedAttempt();
        $session = [AuthService::SESSION_KEY => $quiz['owner']];

        $overview = $this->withSession($session)->get('/results');
        $overview->assertRedirectTo(site_url('dashboard'));

        $report = $this->withSession($session)->get('/results/quizzes/' . $quiz['publicId']);
        $report->assertOK();
        $report->assertSee('Capital cities');
        $report->assertSee('Review');
        $report->assertDontSee('Back to responses');
        $report->assertDontSee('Paper revision');
        $report->assertDontSee('Export filtered CSV');
        $report->assertDontSee('Completed or provisionally expired');
        $body = $report->response()->getBody();
        foreach (['name="q"', 'name="minScore"', 'name="dateRange"'] as $control) {
            self::assertStringContainsString($control, $body);
        }
        foreach (['name="status"', 'name="dateFrom"', 'name="dateTo"', 'name="maxScore"', 'name="sort"'] as $removed) {
            self::assertStringNotContainsString($removed, $body);
        }
        foreach (['Last 24 hours', 'Yesterday', 'Last 7 days', 'Last 30 days', 'All time'] as $dateOption) {
            $report->assertSee($dateOption);
        }
        self::assertStringContainsString('private', $report->response()->getHeaderLine('Cache-Control'));

        $review = $this->withSession($session)->get('/results/attempts/' . $attempt['attemptId']);
        $review->assertOK();
        $review->assertSee('Capital of France?');
        $review->assertSee('Correct');
        $review->assertSee('Back to responses');
    }

    public function testNonOwnedAndMalformedQuizIdsShareTheNotFoundBoundary(): void
    {
        $quiz = $this->quiz();
        foreach ([str_repeat('b', 32), 'malformed'] as $publicId) {
            try {
                $this->withSession([AuthService::SESSION_KEY => $quiz['owner']])
                    ->get('/results/quizzes/' . $publicId);
                self::fail('A non-owned or malformed report should not render.');
            } catch (PageNotFoundException $exception) {
                self::assertSame(404, $exception->getCode());
            }
        }
    }

    public function testDeletedQuizKeepsHistoricalReportWithoutRestoreControl(): void
    {
        [$quiz] = $this->completedAttempt();
        $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'trash');

        $response = $this->withSession([AuthService::SESSION_KEY => $quiz['owner']])
            ->get('/results/quizzes/' . $quiz['publicId']);

        $response->assertOK();
        $response->assertSee('historical responses remain available');
        $response->assertDontSee('dashboard?status=trash');
        $response->assertDontSee('Manage quiz on dashboard');
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function completedAttempt(): array
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        $answers = [$this->submission($attempt, 0), $this->submission($attempt, 1)];
        $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['credential'],
            $this->finishBody($attempt, $answers, 2),
        );
        return [$quiz, $attempt];
    }
}
