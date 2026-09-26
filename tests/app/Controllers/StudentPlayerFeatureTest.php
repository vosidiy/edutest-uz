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
        $response = $this->get('/q/' . $quiz['share']);
        $response->assertOK();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->response()->getBody());
        $response->assertDontSee('correctCodes');
        $response->assertDontSee('csrf-token');
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
}
