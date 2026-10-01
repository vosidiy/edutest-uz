<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\PlayerException;
use App\Services\QuizPaperService;
use App\Services\TeacherResultsService;
use Tests\Support\PlayerTestCase;

final class StudentPlayerTest extends PlayerTestCase
{
    public function testPublishedPaperChangesOnlyAfterExplicitPublish(): void
    {
        $quiz = $this->quiz();
        $oldTicket = $this->player->admission->ticket($quiz['share']);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $oldPaper = $document['publishedRevision'];
        $document['title'] = 'Unpublished working title';
        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);

        $this->assertTrue($saved['hasUnpublishedChanges']);
        $this->assertSame('Capital cities', $this->authoring->publicSummary($quiz['share'])['title']);
        $published = $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'publish')['quiz'];
        $this->assertFalse($published['hasUnpublishedChanges']);
        $this->assertGreaterThan($oldPaper, $published['publishedRevision']);
        $this->assertSame('Unpublished working title', $this->authoring->publicSummary($quiz['share'])['title']);

        $this->expectPlayerError('quiz_changed', fn () => $this->player->admission->start(['ticket' => $oldTicket['ticket'], 'name' => 'Old ticket']));
    }

    public function testStartCreatesEveryAnswerRowAndRetryReturnsSameAttempt(): void
    {
        $quiz = $this->quiz(count: 3);
        $ticket = $this->player->admission->ticket($quiz['share']);
        $first = $this->player->admission->start(['ticket' => $ticket['ticket'], 'name' => 'Alice'], '192.0.2.1', 'Example browser');
        $again = $this->player->admission->start(['ticket' => $ticket['ticket']]);

        $this->assertSame($first, $again);
        $this->assertSame(1, $this->db->table('attempts')->countAllResults());
        $this->assertSame(3, $this->db->table('attempt_answers')->countAllResults());
        $this->assertSame(['not_reached', 'not_reached', 'not_reached'], array_column($this->db->table('attempt_answers')->orderBy('pos')->get()->getResultArray(), 'status'));
        $this->assertSame('Alice', $this->db->table('attempts')->get()->getRow('name'));
    }

    public function testConfirmedAnswersAreIndependentIdempotentAndImmutable(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $first = $this->submission($attempt, 0);
        $body = $this->answerBody($first);
        $saved = $this->player->assessment->answer($attempt['attemptId'], $first['questionId'], $attempt['credential'], $body);
        $again = $this->player->assessment->answer($attempt['attemptId'], $first['questionId'], $attempt['credential'], $body);
        $this->assertSame($saved, $again);
        $this->assertArrayNotHasKey('quiz', $saved);
        $this->assertArrayNotHasKey('items', $saved);

        $second = $this->submission($attempt, 1);
        $this->player->assessment->answer($attempt['attemptId'], $second['questionId'], $attempt['credential'], $this->answerBody($second));
        $this->assertSame(['answered', 'answered'], array_column($this->db->table('attempt_answers')->orderBy('pos')->get()->getResultArray(), 'status'));

        $wrong = $body;
        $wrong['answerCodes'] = [$this->wrongCode($attempt['quiz']['questions'][0])];
        $this->expectPlayerError('answer_locked', fn () => $this->player->assessment->answer($attempt['attemptId'], $first['questionId'], $attempt['credential'], $wrong));
    }

    public function testFinishRequiresEveryQuestionAndUsesServerScoring(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 2));
        $first = $this->submission($attempt, 0);
        $this->player->assessment->answer($attempt['attemptId'], $first['questionId'], $attempt['credential'], $this->answerBody($first));
        $this->expectPlayerError('incomplete_sync', fn () => $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $finishBody ?? $this->finishBody($attempt)));

        $second = $this->submission($attempt, 1);
        $this->player->assessment->answer($attempt['attemptId'], $second['questionId'], $attempt['credential'], $this->answerBody($second));
        $finishBody = $this->finishBody($attempt);
        $finished = $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $finishBody ?? $this->finishBody($attempt));
        $this->assertSame('completed', $finished['status']);
        $this->assertSame('2', $finished['result']['score']);
        $this->assertSame('2', $finished['result']['maxScore']);
        $this->assertSame('100.00', $finished['result']['percent']);
        $this->assertSame($finished['result'], $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $finishBody ?? $this->finishBody($attempt))['result']);
    }

    public function testSkipAndExactSetMultiSelectAreAllOrNothing(): void
    {
        $quiz = $this->quiz(count: 2);
        $doc = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $doc['questions'][0]['type'] = 'multi_select';
        $doc['questions'][0]['options'][0]['isCorrect'] = true;
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $doc);
        $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'publish');
        $attempt = $this->startQuiz($quiz);
        $question = $attempt['quiz']['questions'][0];
        $incomplete = ['status' => 'answered', 'answerCodes' => [$question['correctCodes'][0]], 'textAnswer' => ''];
        $this->player->assessment->answer($attempt['attemptId'], $question['id'], $attempt['credential'], $this->confirmation($incomplete));
        $second = $attempt['quiz']['questions'][1];
        $this->player->assessment->answer($attempt['attemptId'], $second['id'], $attempt['credential'], $this->confirmation(['status' => 'skipped', 'answerCodes' => [], 'textAnswer' => '']));
        $result = $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $finishBody ?? $this->finishBody($attempt));
        $this->assertSame('0', $result['result']['score']);
        $this->assertSame(['wrong', 'unanswered'], array_column($result['result']['items'], 'result'));
    }

    public function testProvisionalAbandonmentCanRecoverWithoutLosingConfirmedHistory(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['timeLimitMinutes' => '0.5']));
        $first = $this->submission($attempt, 0);
        $this->player->assessment->answer($attempt['attemptId'], $first['questionId'], $attempt['credential'], $this->answerBody($first));
        $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update(['expires_at' => '2000-01-01 00:00:00.000000']);
        $terminal = $this->player->assessment->load($attempt['attemptId'], $attempt['credential']);
        $this->assertSame('abandoned', $terminal['status']);
        $this->assertSame('timer_expired', $terminal['finishReason']);
        $this->assertSame('1', $terminal['result']['score']);
        $second = $this->submission($attempt, 1);
        $recovered = $this->player->assessment->answer($attempt['attemptId'], $second['questionId'], $attempt['credential'], $this->answerBody($second));
        $this->assertTrue($recovered['lateSync']);
        $this->assertSame('in_progress', $recovered['status']);
    }

    public function testHistoricalReviewUsesPaperAfterLiveQuestionsAreDeleted(): void
    {
        $quiz = $this->quiz();
        $attempt = $this->startQuiz($quiz);
        foreach ([0, 1] as $index) {
            $answer = $this->submission($attempt, $index);
            $this->player->assessment->answer($attempt['attemptId'], $answer['questionId'], $attempt['credential'], $this->answerBody($answer));
        }
        $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $finishBody ?? $this->finishBody($attempt));
        $doc = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $doc['questions'] = [];
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $doc);

        $report = (new TeacherResultsService($this->db, $this->media, new QuizPaperService($this->db, $this->media)))
            ->attempt($quiz['owner'], $attempt['attemptId'], 'UTC');
        $this->assertCount(2, $report['questions']);
        $this->assertSame(['correct', 'correct'], array_column($report['questions'], 'result'));
    }

    public function testPracticeCreatesNoAttemptOrAnswerRows(): void
    {
        $practice = $this->startQuiz($this->quiz('practice'));
        $this->assertSame('practice', $practice['mode']);
        $this->assertSame(0, $this->db->table('attempts')->countAllResults());
        $this->assertSame(0, $this->db->table('attempt_answers')->countAllResults());
        $this->assertSame(1, $this->db->table('practice_keys')->countAllResults());
    }

    private function answerBody(array $answer): array
    {
        return $this->confirmation($answer);
    }

    private function wrongCode(array $question): string
    {
        foreach ($question['options'] as $option) if (! in_array($option['code'], $question['correctCodes'], true)) return $option['code'];
        self::fail('Fixture requires a wrong option.');
    }

    private function expectPlayerError(string $code, callable $operation): void
    {
        try { $operation(); self::fail("Expected {$code}."); }
        catch (PlayerException $error) { self::assertSame($code, $error->errorCode); }
    }
}
