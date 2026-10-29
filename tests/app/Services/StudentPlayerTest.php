<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\PlayerException;
use App\Services\AttemptSnapshotService;
use App\Services\Player\PlayerStore;
use App\Services\TeacherResultsService;
use Tests\Support\PlayerTestCase;

final class StudentPlayerTest extends PlayerTestCase
{
    public function testStartStoresSnapshotAndIsIdempotentBeforeCheckingLiveVersion(): void
    {
        $quiz = $this->quiz(count: 3);
        $ticket = $this->player->admission->ticket($quiz['share']);
        $first = $this->player->admission->start(['ticket' => $ticket['ticket'], 'name' => 'Alice']);

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['title'] = 'Changed after start';
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        $retry = $this->player->admission->start(['ticket' => $ticket['ticket']]);

        self::assertSame($first, $retry);
        $row = $this->db->table('attempts')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame('[]', $row['answers']);
        self::assertStringContainsString('Capital cities', (string) $row['snapshot']);
        self::assertStringNotContainsString('Changed after start', (string) $row['snapshot']);
    }

    public function testPublishedEditsAffectOnlyFutureStarts(): void
    {
        $quiz = $this->quiz(count: 1);
        $first = $this->startQuiz($quiz);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['questions'][0]['content'] = 'Edited question';
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        $second = $this->startQuiz($quiz);

        self::assertStringStartsWith('Capital of France?', $first['quiz']['questions'][0]['content']);
        self::assertSame('Edited question', $second['quiz']['questions'][0]['content']);
    }

    public function testHigherProgressVersionWinsAndSameVersionMustBeIdentical(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 2));
        $first = $this->submission($attempt, 0);
        $ack = $this->player->assessment->progress($attempt['attemptId'], $attempt['credential'], $this->progressBody([$first], 1));
        self::assertSame(1, $ack['answerVersion']);
        self::assertSame($ack, $this->player->assessment->progress($attempt['attemptId'], $attempt['credential'], $this->progressBody([$first], 1)));

        $changed = $first;
        $changed['answerCode'] = null;
        $this->expectPlayerError('progress_conflict', fn () => $this->player->assessment->progress(
            $attempt['attemptId'], $attempt['credential'], $this->progressBody([$changed], 1),
        ));

        $second = $this->submission($attempt, 1, false);
        $ack = $this->player->assessment->progress($attempt['attemptId'], $attempt['credential'], $this->progressBody([$first, $second], 3));
        self::assertSame(3, $ack['answerVersion']);
    }

    public function testFinishOverridesPrefixAndServerGradesOfficialResult(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 2));
        $first = $this->submission($attempt, 0);
        $this->player->assessment->progress($attempt['attemptId'], $attempt['credential'], $this->progressBody([$first], 1));
        $second = $this->submission($attempt, 1, false);
        $body = $this->finishBody($attempt, [$first, $second], 2);
        $finished = $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $body);

        self::assertSame('completed', $finished['status']);
        self::assertSame(1, $finished['result']['score']);
        self::assertSame('50.00', $finished['result']['percent']);
        self::assertSame($finished, $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $body));
    }

    public function testExitFillsUnreachedTailAndRejectsFurtherProgress(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 2));
        $first = $this->submission($attempt, 0);
        $tail = ['questionId' => $attempt['quiz']['questions'][1]['id'], 'answerCode' => null, 'answeredAt' => null];
        $finished = $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['credential'],
            $this->finishBody($attempt, [$first, $tail], 1, 'exited'),
        );
        self::assertSame('exited', $finished['finishReason']);
        $this->expectPlayerError('attempt_finalized', fn () => $this->player->assessment->progress(
            $attempt['attemptId'], $attempt['credential'], $this->progressBody([$first], 2),
        ));
    }

    public function testNormalCompletionCannotContainAnUnreachedTail(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 2));
        $first = $this->submission($attempt, 0);
        $tail = ['questionId' => $attempt['quiz']['questions'][1]['id'], 'answerCode' => null, 'answeredAt' => null];

        $this->expectPlayerError('invalid_progress', fn () => $this->player->assessment->finish(
            $attempt['attemptId'],
            $attempt['credential'],
            $this->finishBody($attempt, [$first, $tail], 1, 'completed'),
        ));
    }

    public function testLazyExpiryIsProvisionalAndDelayedFinishCanReplaceIt(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 1));
        $answer = $this->submission($attempt, 0);
        $this->player->assessment->progress($attempt['attemptId'], $attempt['credential'], $this->progressBody([$answer], 1));
        $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update([
            'started_at' => '1999-12-31 23:00:00.000000',
            'expires_at' => '2000-01-01 00:00:00.000000',
        ]);
        $provisional = $this->player->assessment->load($attempt['attemptId'], $attempt['credential']);
        self::assertSame('abandoned', $provisional['status']);
        self::assertTrue($provisional['result']['provisional']);

        // Simulate a browser record made at the original deadline. It remains valid even
        // though the server resolved the attempt lazily before receiving it.
        $answer['answeredAt'] = '1999-12-31T23:30:00.000Z';
        $body = $this->finishBody($attempt, [$answer], 2, 'expired', PlayerStore::iso('2000-01-01 00:00:00.000000'));
        $finished = $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $body);
        self::assertSame('completed', $finished['status']);
        self::assertTrue($finished['lateSync']);
    }

    public function testHistoricalReportUsesSnapshotAfterLiveQuestionDeletion(): void
    {
        $quiz = $this->quiz(count: 1);
        $attempt = $this->startQuiz($quiz);
        $answer = $this->submission($attempt, 0);
        $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $this->finishBody($attempt, [$answer], 1));
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['questions'] = [];
        $document['status'] = 'draft';
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);

        $report = (new TeacherResultsService($this->db, $this->media, new AttemptSnapshotService($this->db, $this->media), $this->player))
            ->attempt($quiz['owner'], $attempt['attemptId'], 'UTC');
        self::assertCount(1, $report['questions']);
        self::assertSame('correct', $report['questions'][0]['result']);
    }

    private function expectPlayerError(string $code, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$code}.");
        } catch (PlayerException $error) {
            self::assertSame($code, $error->errorCode);
        }
    }
}
