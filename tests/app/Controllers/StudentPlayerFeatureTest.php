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
    }

    public function testStartAndResultsShellUseTheNewRoutes(): void
    {
        $quiz = $this->quiz();
        $this->get('/q/' . $quiz['share'] . '/start')->assertOK();
        $this->get('/q/' . $quiz['share'] . '/results')->assertOK();
        try {
            $this->get('/q/' . $quiz['share'] . '/play');
            self::fail('The removed /play route must not resolve.');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $error) {
            self::assertSame(404, $error->getCode());
        }
    }

    public function testPlayerApiRequiresCustomHeaderAndBearer(): void
    {
        $quiz = $this->quiz();
        $this->get('/api/v1/player/tickets/' . $quiz['share'])->assertStatus(403);
        $ticket = $this->withHeaders(['X-EduTest-Player' => '1'])->get('/api/v1/player/tickets/' . $quiz['share']);
        $ticket->assertOK();
        $this->withHeaders(['X-EduTest-Player' => '1', 'Authorization' => 'Bearer ' . str_repeat('0', 64)])
            ->get('/api/v1/player/assessments/' . str_repeat('a', 32))->assertStatus(401);
    }

    public function testProgressAndFinishUseWholeAnswerDocuments(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 1));
        $answer = $this->submission($attempt, 0);
        $base = '/api/v1/player/assessments/' . $attempt['attemptId'];
        $headers = [
            'X-EduTest-Player' => '1',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $attempt['credential'],
        ];
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
        $headers = ['X-EduTest-Player' => '1', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $attempt['credential']];
        $this->withHeaders($headers)->withBody('{broken')->put($base . '/progress')->assertStatus(400);
        $answer = $this->submission($attempt, 0);
        $answer['answerCode'] = 'foreign-code';
        $this->withHeaders($headers)->withBodyFormat('json')->put($base . '/progress', $this->progressBody([$answer], 1))->assertStatus(422);
    }
}
