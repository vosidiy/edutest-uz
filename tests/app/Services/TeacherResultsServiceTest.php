<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\ReportingException;
use App\Services\AttemptSnapshotService;
use App\Services\TeacherResultsService;
use Tests\Support\PlayerTestCase;

final class TeacherResultsServiceTest extends PlayerTestCase
{
    private TeacherResultsService $results;

    protected function setUp(): void
    {
        parent::setUp();
        $this->results = new TeacherResultsService(
            $this->db,
            $this->media,
            new AttemptSnapshotService($this->db, $this->media),
            $this->player,
        );
    }

    public function testQuizReportReadsCountsAndCorrectnessFromAttemptDocuments(): void
    {
        $quiz = $this->quiz(count: 2);
        $completed = $this->startQuiz($quiz);
        $this->complete($completed);
        $this->startQuiz($quiz);
        $expired = $this->startQuiz($quiz);
        $this->db->table('attempts')->where('public_id', $expired['attemptId'])->update([
            'expires_at' => '2000-01-01 00:00:00.000000',
        ]);

        $report = $this->results->quiz($quiz['owner'], $quiz['publicId'], [], 'UTC');
        self::assertSame(2, $report['metrics']['finalizedAttempts']);
        self::assertSame(1, $report['metrics']['inProgressAttempts']);
        self::assertCount(3, $report['attempts']);
        $byStatus = array_column($report['attempts'], null, 'status');
        self::assertSame(2, $byStatus['completed']['responsesReceived']);
        self::assertSame(2, $byStatus['completed']['correctReceived']);
        self::assertSame(0, $byStatus['in_progress']['responsesReceived']);
        self::assertSame(0, $byStatus['abandoned']['responsesReceived']);
    }

    public function testStatusFiltersAndCsvProtectFormulaCells(): void
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        $this->complete($attempt);
        $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update(['name' => '=Formula']);

        $report = $this->results->quiz($quiz['owner'], $quiz['publicId'], ['status' => 'completed'], 'UTC');
        self::assertCount(1, $report['attempts']);
        $rows = iterator_to_array($this->results->export($quiz['owner'], $quiz['publicId'], ['status' => 'completed'], 'UTC')['rows']);
        self::assertSame('=Formula', $rows[0]['name']);
        self::assertSame("'=Formula", $this->results->protectCsvCell($rows[0]['name']));
        self::assertArrayNotHasKey('ip', $rows[0]);
        self::assertArrayNotHasKey('agent', $rows[0]);
    }

    public function testAttemptReviewKeepsTheImmutableSnapshotAfterLiveEdits(): void
    {
        $quiz = $this->quiz(settings: ['shuffleOptions' => true]);
        $attempt = $this->startQuiz($quiz);
        $this->complete($attempt);
        $before = array_column($attempt['quiz']['questions'][0]['options'], 'content');

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['questions'][0]['content'] = 'A future-start edit';
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        $report = $this->results->attempt($quiz['owner'], $attempt['attemptId'], 'Asia/Tashkent');

        self::assertSame('completed', $report['attempt']['status']);
        self::assertSame($before, array_column($report['questions'][0]['options'], 'content'));
        self::assertSame(['A', 'B'], array_column($report['questions'][0]['options'], 'label'));
        self::assertNotNull($report['questions'][0]['answeredAt']);
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        foreach (['token_hash', 'start_key', 'media_src'] as $private) {
            self::assertStringNotContainsString($private, $json);
        }
    }

    public function testNonOwnerAndMalformedAttemptsShareTheNotFoundBoundary(): void
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        foreach ([[$quiz['owner'] + 999, $attempt['attemptId']], [$quiz['owner'], 'bad-id']] as [$owner, $id]) {
            try {
                $this->results->attempt($owner, $id, 'UTC');
                self::fail('Expected a reporting not-found error.');
            } catch (ReportingException $error) {
                self::assertSame(404, $error->status);
            }
        }
    }

    public function testExitIsACompletedResultWithSkippedTail(): void
    {
        $quiz = $this->quiz(count: 2);
        $attempt = $this->startQuiz($quiz);
        $first = $this->submission($attempt, 0);
        $tail = ['questionId' => $attempt['quiz']['questions'][1]['id'], 'answerCode' => null, 'answeredAt' => null];
        $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['credential'],
            $this->finishBody($attempt, [$first, $tail], 1, 'exited'),
        );

        $report = $this->results->quiz($quiz['owner'], $quiz['publicId'], [], 'UTC');
        self::assertSame('completed', $report['attempts'][0]['status']);
        self::assertSame('exited', $report['attempts'][0]['finishReason']);
        self::assertSame(2, $report['attempts'][0]['responsesReceived']);
        self::assertSame(1, $report['attempts'][0]['correctReceived']);
        self::assertSame('50.00', $report['attempts'][0]['percent']);
    }

    private function complete(array $attempt): void
    {
        $answers = [];
        foreach (array_keys($attempt['quiz']['questions']) as $index) {
            $answers[] = $this->submission($attempt, $index);
        }
        $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['credential'],
            $this->finishBody($attempt, $answers, count($answers)),
        );
    }
}
