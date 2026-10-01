<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\ReportingException;
use App\Services\QuizPaperService;
use App\Services\TeacherResultsService;
use Tests\Support\PlayerTestCase;

final class TeacherResultsServiceTest extends PlayerTestCase
{
    private TeacherResultsService $results;

    protected function setUp(): void
    {
        parent::setUp();
        $this->results = new TeacherResultsService($this->db, $this->media, new QuizPaperService($this->db, $this->media));
    }

    public function testQuizReportCountsCompletedInProgressAndLazilyAbandonedAttempts(): void
    {
        $quiz = $this->quiz(count: 2);
        $completed = $this->startQuiz($quiz);
        $this->complete($completed);
        $this->startQuiz($quiz);
        $expired = $this->startQuiz($quiz);
        $this->db->table('attempts')->where('public_id', $expired['attemptId'])->update(['expires_at' => '2000-01-01 00:00:00.000000']);

        $report = $this->results->quiz($quiz['owner'], $quiz['publicId'], [], 'UTC');
        $this->assertSame(2, $report['metrics']['finalizedAttempts']);
        $this->assertSame(1, $report['metrics']['inProgressAttempts']);
        $this->assertSame(['abandoned', 'completed'], array_column($report['attempts'], 'status'));
    }

    public function testStatusFiltersAndCsvUseNewLifecycleNames(): void
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        $this->complete($attempt);
        $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update(['name' => '=Formula']);

        $report = $this->results->quiz($quiz['owner'], $quiz['publicId'], ['status' => 'completed'], 'UTC');
        $this->assertCount(1, $report['attempts']);
        $this->assertSame('completed', $report['attempts'][0]['status']);
        $rows = iterator_to_array($this->results->export($quiz['owner'], $quiz['publicId'], ['status' => 'completed'], 'UTC')['rows']);
        $this->assertSame('=Formula', $rows[0]['name']);
        $this->assertSame("'=Formula", $this->results->protectCsvCell($rows[0]['name']));
        $this->assertArrayNotHasKey('ip', $rows[0]);
    }

    public function testAttemptReviewUsesNormalizedRowsAndImmutablePaperOrder(): void
    {
        $quiz = $this->quiz(settings: ['shuffleOptions' => true]);
        $attempt = $this->startQuiz($quiz);
        $this->complete($attempt);
        $before = array_column($attempt['quiz']['questions'][0]['options'], 'content');

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['questions'] = [];
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        $report = $this->results->attempt($quiz['owner'], $attempt['attemptId'], 'Asia/Tashkent');

        $this->assertSame('completed', $report['attempt']['status']);
        $this->assertSame($before, array_column($report['questions'][0]['options'], 'content'));
        $this->assertSame(['A', 'B'], array_column($report['questions'][0]['options'], 'label'));
        $this->assertNotNull($report['questions'][0]['answeredAt']);
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        foreach (['token_hash', 'start_key', 'passcode_hash', 'media_src'] as $private) self::assertStringNotContainsString($private, $json);
    }

    public function testNonOwnerAndMalformedAttemptsReturnTheSameNotFoundError(): void
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        foreach ([str_repeat('f', 32), 'bad-id'] as $id) {
            try { $this->results->attempt($quiz['owner'] + 999, $id === 'bad-id' ? $id : $attempt['attemptId'], 'UTC'); self::fail(); }
            catch (ReportingException $error) { self::assertSame(404, $error->status); }
        }
    }

    private function complete(array $attempt): void
    {
        foreach ($attempt['quiz']['questions'] as $index => $question) {
            $submission = $this->submission($attempt, $index);
            $this->player->assessment->answer($attempt['attemptId'], $question['id'], $attempt['credential'], $this->confirmation([
                'status' => $submission['status'], 'answerCodes' => $submission['answerCodes'], 'textAnswer' => $submission['textAnswer'],
            ]));
        }
        $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $this->finishBody($attempt));
    }
}
