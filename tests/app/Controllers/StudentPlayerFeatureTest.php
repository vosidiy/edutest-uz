<?php

declare(strict_types=1);

namespace Tests\App\Controllers;

use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\PlayerTestCase;

final class StudentPlayerFeatureTest extends PlayerTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('player', $this->player);
        Services::injectMock('quizAuthoring', $this->authoring);
        Services::injectMock('media', $this->media);
    }

    public function testIntroductionIsAssessmentOnlyAndDoesNotPreloadAnswerKeys(): void
    {
        $quiz = $this->quiz(settings: ['askEmail' => true, 'askPhone' => true]);
        $response = $this->get('/q/' . $quiz['share']);
        $response->assertOK();
        $response->assertSee('Start new quiz');
        $response->assertSee('Your name');
        $response->assertSee('Email');
        $response->assertSee('Phone');
        $response->assertDontSee('correctCode');
        $response->assertDontSee('Practice');
        $response->assertDontSee('player-question-template');
    }

    public function testStartAndResultsShellUseTheNewRoutes(): void
    {
        $quiz = $this->quiz();
        $this->get('/q/' . $quiz['share'] . '/start')->assertRedirectTo(site_url('q/' . $quiz['share']));
        $this->get('/q/' . $quiz['share'] . '/results')->assertRedirectTo(site_url('q/' . $quiz['share']));

        $attempt = $this->startQuiz($quiz);
        $this->setAttemptCookie($quiz['share'], $attempt['attemptId']);
        $this->get('/q/' . $quiz['share'])->assertRedirectTo(site_url('q/' . $quiz['share'] . '/start'));
        $start = $this->get('/q/' . $quiz['share'] . '/start');
        $start->assertOK();
        $start->assertSee('player-question-template');
        $start->assertDontSee('quiz-admission');
        $this->get('/q/' . $quiz['share'] . '/results')->assertRedirectTo(site_url('q/' . $quiz['share'] . '/start'));

        $answer = $this->submission($attempt, 0);
        $tail = $this->submission($attempt, 1);
        $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['attemptId'],
            $this->finishBody($attempt, [$answer, $tail], 1),
        );
        $this->get('/q/' . $quiz['share'] . '/start')->assertRedirectTo(site_url('q/' . $quiz['share'] . '/results'));
        $this->get('/q/' . $quiz['share'] . '/results')->assertOK();
        $this->get('/q/' . $quiz['share'])->assertOK();
        try {
            $this->get('/q/' . $quiz['share'] . '/play');
            self::fail('The removed /play route must not resolve.');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $error) {
            self::assertSame(404, $error->getCode());
        }
    }

    public function testStartSetsAttemptCookieAndPlayerApiRequiresIt(): void
    {
        $quiz = $this->quiz();
        $this->get('/api/v1/player/tickets/' . $quiz['share'])->assertStatus(403);
        $ticket = $this->withHeaders(['X-EduTest-Player' => '1'])->get('/api/v1/player/tickets/' . $quiz['share']);
        $ticket->assertOK();
        $ticket->assertDontSee('quizVersion');
        $ticketValue = json_decode($ticket->response()->getBody(), true)['data']['ticket'];
        $started = $this->withHeaders(['X-EduTest-Player' => '1', 'Content-Type' => 'application/json'])
            ->withBodyFormat('json')->post('/api/v1/player/starts', ['ticket' => $ticketValue, 'name' => 'Cookie Student']);
        $started->assertOK();
        $data = json_decode($started->response()->getBody(), true)['data'];
        self::assertArrayNotHasKey('credential', $data);
        $cookieName = $this->player->sessions->cookieName($quiz['share']);
        $started->assertCookie($cookieName, $data['attemptId']);
        $cookie = $started->response()->getCookie($cookieName);
        self::assertTrue($cookie->isHTTPOnly());
        self::assertSame('Strict', $cookie->getSameSite());
        self::assertGreaterThan(time() + 25 * 3600, $cookie->getExpiresTimestamp());

        $this->withHeaders(['X-EduTest-Player' => '1'])
            ->get('/api/v1/player/assessments/' . $data['attemptId'])->assertStatus(401);
        $this->setAttemptCookie($quiz['share'], str_repeat('f', 32));
        $this->withHeaders(['X-EduTest-Player' => '1'])
            ->get('/api/v1/player/assessments/' . $data['attemptId'])->assertStatus(401);
        $this->setAttemptCookie($quiz['share'], $data['attemptId']);
        $this->withHeaders(['X-EduTest-Player' => '1'])
            ->get('/api/v1/player/assessments/' . $data['attemptId'])->assertOK();
    }

    public function testProgressAndFinishUseWholeAnswerDocuments(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 1));
        $answer = $this->submission($attempt, 0);
        $base = '/api/v1/player/assessments/' . $attempt['attemptId'];
        $headers = [
            'X-EduTest-Player' => '1',
            'Content-Type' => 'application/json',
        ];
        $this->setAttemptCookie($attempt['quiz']['quiz']['shareToken'], $attempt['attemptId']);
        $this->withHeaders($headers)->withBodyFormat('json')->put(
            $base . '/progress',
            $this->progressBody([$answer], 1),
        )->assertOK();
        $finish = $this->withHeaders($headers)->withBodyFormat('json')->post(
            $base . '/finish',
            $this->finishBody($attempt, [$answer], 2),
        );
        $finish->assertOK();
        $data = json_decode($finish->response()->getBody(), true)['data'];
        self::assertSame('completed', $data['status']);
        self::assertSame(1, $data['result']['score']);
    }

    public function testMalformedJsonAndForeignAnswerCodesAreRejected(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 1));
        $base = '/api/v1/player/assessments/' . $attempt['attemptId'];
        $headers = ['X-EduTest-Player' => '1', 'Content-Type' => 'application/json'];
        $this->setAttemptCookie($attempt['quiz']['quiz']['shareToken'], $attempt['attemptId']);
        $this->withHeaders($headers)->withBody('{broken')->put($base . '/progress')->assertStatus(400);
        $answer = $this->submission($attempt, 0);
        $answer['answerCode'] = 'foreign-code';
        $this->withHeaders($headers)->withBodyFormat('json')->put($base . '/progress', $this->progressBody([$answer], 1))->assertStatus(422);
    }

    private function setAttemptCookie(string $shareToken, string $publicId): void
    {
        service('superglobals')->setCookie($this->player->sessions->cookieName($shareToken), $publicId);
    }
}
