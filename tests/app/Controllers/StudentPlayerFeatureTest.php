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

    public function testPublicIntroductionEscapesContentAndNeverPreloadsKeys(): void
    {
        $quiz = $this->quiz();
        $doc = $quiz['document']; $doc['title'] = '<script>alert(1)</script>';
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $doc);
        $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'publish');
        $response = $this->get('/q/' . $quiz['share']);
        $response->assertOK();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->response()->getBody());
        $response->assertDontSee('correctCodes');
        $response->assertDontSee('csrf-token');
        $response->assertSee('Start quiz');
    }

    public function testShortAndLegacyShareLinksAreAcceptedButMalformedLinksAreHidden(): void
    {
        $quiz = $this->quiz();
        $this->assertMatchesRegularExpression('/^[0-9]{9}$/D', $quiz['share']);
        $this->get('/q/' . $quiz['share'])->assertOK();

        $legacy = str_repeat('a', 64);
        $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(['share_token' => $legacy]);
        $this->get('/q/' . $legacy)->assertOK();
        $this->get('/q/' . $legacy . '/play')->assertOK();
        $this->withHeaders(['X-EduTest-Player' => '1'])->get('/api/v1/player/tickets/' . $legacy)->assertOK();

        foreach (['/q/not-a-code', '/q/not-a-code/play'] as $path) {
            try {
                $this->get($path);
                $this->fail('Malformed public quiz codes must return not found.');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->withHeaders(['X-EduTest-Player' => '1'])->get('/api/v1/player/tickets/not-a-code')->assertStatus(404);
    }

    public function testIntegrityDisclosureOffersNonBlockingFullscreenControl(): void
    {
        $quiz = $this->quiz(settings: ['cheatCheck' => true]);
        $response = $this->get('/q/' . $quiz['share']);

        $response->assertOK();
        $response->assertSee('Enable fullscreen');
        $response->assertSee('quiz tab is hidden');
        $response->assertSee('Start quiz');
    }

    public function testStudentApiRequiresSameOriginCustomHeader(): void
    {
        $quiz = $this->quiz('practice');
        $this->get('/api/v1/player/tickets/' . $quiz['share'])->assertStatus(403);
        $this->withHeaders(['X-EduTest-Player' => '1', 'Origin' => 'https://foreign.example'])->get('/api/v1/player/tickets/' . $quiz['share'])->assertStatus(403);
        $response = $this->withHeaders(['X-EduTest-Player' => '1'])->get('/api/v1/player/tickets/' . $quiz['share']);
        $response->assertOK();
        $response->assertDontSee('csrfToken');
        self::assertArrayNotHasKey('student', json_decode($response->response()->getBody(), true)['data']);
    }

    public function testPracticeStartsWithoutTeacherSessionOrCsrfAndRejectsAnswerBodies(): void
    {
        $quiz = $this->quiz('practice');
        $ticket = $this->player->admission->ticket($quiz['share']);
        $response = $this->withHeaders(['X-EduTest-Player' => '1', 'Content-Type' => 'application/json'])
            ->withBodyFormat('json')->post('/api/v1/player/starts', ['ticket' => $ticket['ticket']]);
        $response->assertOK();
        $response->assertDontSee('csrfToken');
        $this->assertSame(0, $this->db->table('attempts')->countAllResults());
        $credential = json_decode($response->response()->getBody(), true)['data']['credential'];
        $this->withHeaders(['X-EduTest-Player' => '1', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $credential])
            ->withBodyFormat('json')->post('/api/v1/player/practice/media', ['answers' => ['secret']])->assertStatus(422);
    }

    public function testResultsRejectWrongBearerAndTeacherPagesStillRequireAuthentication(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $this->withHeaders(['X-EduTest-Player' => '1', 'Authorization' => 'Bearer ' . str_repeat('0', 64)])
            ->get('/api/v1/player/assessments/' . $attempt['attemptId'] . '/results')->assertStatus(401);
        $this->get('/quizzes')->assertRedirectTo(site_url('login'));
    }

    public function testEmptyAndNonObjectJsonHaveValidationErrors(): void
    {
        foreach (['', '[]', 'null', '{broken'] as $body) {
            $response = $this->withHeaders(['X-EduTest-Player' => '1', 'Content-Type' => 'application/json'])
                ->withBody($body)->post('/api/v1/player/starts');
            $response->assertStatus(400);
            $response->assertSee('invalid_json');
        }
    }

    public function testActivityAndSmallAnswerAcknowledgementsUseBearerJsonBoundary(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $base = '/api/v1/player/assessments/' . $attempt['attemptId'];
        $headers = ['X-EduTest-Player' => '1', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $attempt['credential']];
        $at = \App\Services\Player\PlayerStore::iso(\App\Services\Player\PlayerStore::now());
        $this->withHeaders($headers)->withBodyFormat('json')->post($base . '/activity', ['clientActivityAt' => $at])->assertOK();
        $response = $this->withHeaders($headers)->withBodyFormat('json')->put($base . '/answers/' . $attempt['items'][0]['questionId'], $this->confirmation($this->submission($attempt, 0)));
        $response->assertOK();
        $data = json_decode($response->response()->getBody(), true)['data'];
        self::assertSame($attempt['attemptId'], $data['attemptId']);
        self::assertArrayNotHasKey('quiz', $data);
        self::assertArrayNotHasKey('items', $data);
        $this->withHeaders(['X-EduTest-Player' => '1', 'Content-Type' => 'text/plain'])
            ->withBodyFormat('')->withBody('{}')->put($base . '/answers/' . $attempt['items'][0]['questionId'])->assertStatus(415);
        $this->withHeaders(['X-EduTest-Player' => '1', 'Content-Type' => 'application/json'])
            ->withBodyFormat('json')->post($base . '/activity', ['clientActivityAt' => $at])->assertStatus(401);
    }

    public function testAssessmentStartReturnsIdentityOnlyInTheAuthenticatedFullState(): void
    {
        $quiz = $this->quiz(settings: ['emailMode' => 'optional']);
        $ticket = $this->player->admission->ticket($quiz['share']);
        $response = $this->withHeaders(['X-EduTest-Player' => '1', 'Content-Type' => 'application/json'])
            ->withBodyFormat('json')->post('/api/v1/player/starts', [
                'ticket' => $ticket['ticket'],
                'name' => '<Student>',
                'email' => 'student@example.test',
            ]);
        $response->assertOK();
        $data = json_decode($response->response()->getBody(), true)['data'];
        self::assertSame(['name' => '<Student>', 'email' => 'student@example.test'], $data['student']);

        $answer = $this->confirmation($this->submission($data, 0));
        $ack = $this->withHeaders([
            'X-EduTest-Player' => '1',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $data['credential'],
        ])->withBodyFormat('json')->put(
            '/api/v1/player/assessments/' . $data['attemptId'] . '/answers/' . $data['items'][0]['questionId'],
            $answer,
        );
        $ack->assertOK();
        self::assertArrayNotHasKey('student', json_decode($ack->response()->getBody(), true)['data']);
    }
}
