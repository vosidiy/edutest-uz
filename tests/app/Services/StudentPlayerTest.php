<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\AuthoringException;
use App\Exceptions\PlayerException;
use Tests\Support\PlayerTestCase;

final class StudentPlayerTest extends PlayerTestCase
{
    public function testStartCreatesOnePaperAndRetryRecoversSameAttempt(): void
    {
        $quiz = $this->quiz();
        $ticket = $this->player->admission->ticket($quiz['share']);
        $body = ['ticket' => $ticket['ticket'], 'name' => 'Student', 'userId' => '999'];
        $first = $this->player->admission->start($body);
        $this->assertSame($first, $this->player->admission->start($body));
        $this->assertSame($ticket['credential'], $first['credential']);
        $this->assertSame(1, $this->db->table('attempts')->countAllResults());
        $started = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $this->assertTrue($started['hasStarted']);
        $this->assertArrayNotHasKey('modeLocked', $started);
        $this->assertSame($quiz['document']['version'], $started['version']);
        $this->assertSame(1, $this->db->table('quiz_papers')->countAllResults());
        $attempt = $this->db->table('attempts')->get()->getRowArray();
        $this->assertSame(hash('sha256', $first['credential'], true), $attempt['token_hash']);
        $this->assertSame('Student', $attempt['name']);
        $responses = json_decode($attempt['responses'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $responses['schemaVersion']);
        $this->assertCount(2, $responses['items']);
        $this->assertSame('active', $responses['items'][0]['s']);
        $this->assertEqualsCanonicalizing(['q', 'o'], array_keys($responses['items'][1]));

        $second = $this->startQuiz($quiz);
        $this->assertNotSame($first['attemptId'], $second['attemptId']);
        $this->assertSame(1, $this->db->table('quiz_papers')->countAllResults());
    }

    public function testModeChangesAffectOnlyFutureStartsAndPreserveBoundRuns(): void
    {
        $quiz = $this->quiz('assessment');
        $assessment = $this->startQuiz($quiz);
        $this->player->assessment->sync($assessment['attemptId'], $assessment['credential'], [
            'version' => $assessment['version'],
            'items' => [$this->submission($assessment, 0), $this->submission($assessment, 1)],
            'finishReason' => 'completed',
        ]);
        $staleTicket = $this->player->admission->ticket($quiz['share']);

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $assessmentRevision = $document['revision'];
        $document['mode'] = 'practice';
        $practiceDocument = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);

        $this->assertSame('practice', $practiceDocument['mode']);
        $this->assertSame($assessmentRevision + 1, $practiceDocument['revision']);
        $this->assertSame('assessment', $this->player->assessment->load($assessment['attemptId'], $assessment['credential'])['mode']);
        try {
            $this->player->admission->start(['ticket' => $staleTicket['ticket'], 'name' => 'Stale Student']);
            $this->fail('A ticket issued before a mode change must be rejected.');
        } catch (PlayerException $exception) {
            $this->assertSame('quiz_changed', $exception->errorCode);
        }

        $practiceTicket = $this->player->admission->ticket($quiz['share']);
        $this->assertSame('practice', $practiceTicket['mode']);
        $practice = $this->player->admission->start(['ticket' => $practiceTicket['ticket']]);
        $this->assertSame('practice', $practice['mode']);
        $practicePaperId = (int) $this->db->table('practice_keys')->get()->getRow('paper_id');

        $practiceDocument['mode'] = 'assessment';
        $assessmentDocument = $this->authoring->save($quiz['owner'], $quiz['publicId'], $practiceDocument);
        $this->assertSame('assessment', $assessmentDocument['mode']);
        $this->assertSame($practiceDocument['revision'] + 1, $assessmentDocument['revision']);
        $this->assertSame('practice', $this->player->practice->media($practice['credential'])['quiz']['settings']['mode']);

        $this->db->table('practice_keys')->update(['expires_at' => '2000-01-01 00:00:00.000000']);
        $nextTicket = $this->player->admission->ticket($quiz['share']);
        $nextAssessment = $this->player->admission->start(['ticket' => $nextTicket['ticket'], 'name' => 'New Student']);
        $this->assertSame('assessment', $nextAssessment['mode']);
        $this->assertSame(2, $this->db->table('attempts')->countAllResults());
        $this->assertSame(0, $this->db->table('practice_keys')->countAllResults());
        $this->assertSame(0, $this->db->table('quiz_papers')->where('id', $practicePaperId)->countAllResults());

        $library = (new \App\Services\TeacherQueryService($this->db))->library($quiz['owner'], [], 'active');
        $this->assertSame(1, $library['rows'][0]['assessmentSubmissions']);
        $this->assertSame(1, $library['rows'][0]['practiceStarts']);
    }

    public function testPreloadUsesCodesWithoutOptionCorrectnessOrPrivatePaths(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['showAnswers' => true, 'showExplain' => true]));
        $question = $attempt['quiz']['questions'][0];
        $this->assertCount(1, $question['correctCodes']);
        $this->assertArrayNotHasKey('isCorrect', $question['options'][0]);
        $this->assertNotEmpty($question['explanation']);
        $json = json_encode($attempt);
        foreach (['media_src', 'passcode_hash', 'token_hash', 'writable/', 'responses', 'submitHash'] as $private) $this->assertStringNotContainsString($private, $json);
        $this->assertIsString($question['id']);
    }

    public function testPaperMediaKeysAreOpaqueAndNotLiveRecordIdentifiers(): void
    {
        $quiz = $this->quiz();
        $quizRow = $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->get()->getRowArray();
        $question = $this->db->table('questions')->where('quiz_id', $quizRow['id'])->orderBy('pos')->get()->getRowArray();
        $this->db->table('questions')->where('id', $question['id'])->update([
            'media_type' => 'video',
            'media_src' => 'https://vimeo.com/123456789',
        ]);

        $this->startQuiz($quiz);
        $paper = $this->db->table('quiz_papers')->get()->getRowArray();
        $definition = json_decode((string) $paper['definition'], true, 512, JSON_THROW_ON_ERROR);
        $key = $definition['questions'][0]['media']['key'];

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $key);
        $this->assertNotSame('question:' . $question['id'], $key);
    }


    public function testOrderedSyncScoresOnServerAndReplaysWithoutIncrement(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['showAnswers' => true]));
        $payload = ['version' => $attempt['version'], 'items' => [$this->submission($attempt, 0), $this->submission($attempt, 1)], 'finishReason' => 'completed', 'score' => '999.99'];
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], $payload);
        $this->assertSame('submitted', $saved['status']);
        $this->assertSame('2.00', $saved['result']['score']);
        $this->assertSame('100.00', $saved['result']['percent']);
        $again = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], $payload);
        $this->assertSame($saved['version'], $again['version']);
        $payload['items'][0]['answerCodes'] = [$attempt['quiz']['questions'][0]['options'][0]['code']];
        $this->expectException(PlayerException::class);
        $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], $payload);
    }

    public function testInvalidLaterItemRollsBackEarlierSubmission(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $bad = $this->submission($attempt, 1); $bad['answerCodes'] = ['foreign-code'];
        try { $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$this->submission($attempt, 0), $bad]]); $this->fail('Invalid answer accepted'); }
        catch (PlayerException $error) { $this->assertSame('invalid_answer', $error->errorCode); }
        $saved = $this->player->assessment->load($attempt['attemptId'], $attempt['credential']);
        $this->assertSame('active', $saved['items'][0]['status']);
        $this->assertSame(1, $saved['version']);
    }

    public function testSubmissionKeyReuseRollsBackTheWholeResponseDocument(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $before = $this->db->table('attempts')->get()->getRow('responses');
        $first = $this->submission($attempt, 0);
        $second = $this->submission($attempt, 1);
        $second['submitKey'] = $first['submitKey'];
        try {
            $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$first, $second]]);
            $this->fail('Reused submission key accepted');
        } catch (PlayerException $error) { $this->assertSame('invalid_progress', $error->errorCode); }
        $this->assertSame($before, $this->db->table('attempts')->get()->getRow('responses'));
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$first]]);
        try {
            $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => $saved['version'], 'items' => [$second]]);
            $this->fail('Reused persisted submission key accepted');
        } catch (PlayerException $error) { $this->assertSame('invalid_progress', $error->errorCode); }
        $this->assertSame($saved['version'], (int) $this->db->table('attempts')->get()->getRow('version'));
    }

    public function testDraftRefreshSkipAndAcknowledgementReplayPreserveResponses(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['shuffleOptions' => true]));
        $draft = $this->submission($attempt, 0); $draft['submitKey'] = null;
        $payload = ['version' => 1, 'items' => [$draft]];
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], $payload);
        $before = $this->db->table('attempts')->get()->getRow('responses');
        $again = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], $payload);
        $this->assertSame($saved['version'], $again['version']);
        $this->assertSame($before, $this->db->table('attempts')->get()->getRow('responses'));
        $this->assertSame($draft['answerCodes'], $again['items'][0]['answerCodes']);
        $this->assertSame($attempt['quiz']['questions'], $again['quiz']['questions']);
        $skip = $this->submission($attempt, 0); $skip['saveVer'] = 2; $skip['reason'] = 'skipped';
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => $saved['version'], 'items' => [$skip, $this->submission($attempt, 1)], 'finishReason' => 'completed']);
        $this->assertSame([], $saved['items'][0]['answerCodes']);
        $this->assertSame('unanswered', $saved['result']['items'][0]['result']);
        $this->assertSame('1.00', $saved['result']['score']);
        $this->assertStringNotContainsString('submitHash', json_encode($saved, JSON_THROW_ON_ERROR));
    }

    public function testStaleVersionRollsBackAndDraftsCannotJumpQuestions(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $input = $this->submission($attempt, 0); $input['submitKey'] = null;
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$input]]);
        $input['saveVer'] = 2; $input['textAnswer'] = ''; $input['answerCodes'] = [];
        try { $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$input]]); $this->fail(); }
        catch (PlayerException $error) { $this->assertSame('progress_conflict', $error->errorCode); }
        $this->assertSame($saved['items'][0]['answerCodes'], $this->player->assessment->load($attempt['attemptId'], $attempt['credential'])['items'][0]['answerCodes']);
        $this->expectException(PlayerException::class);
        $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 2, 'items' => [$this->submission($attempt, 1)]]);
    }

    public function testMixedAnswersRemainReviewableAfterLiveQuestionsAreDeleted(): void
    {
        $quiz = $this->quiz(settings: ['shuffleOptions' => true, 'showScore' => false], count: 3);
        $doc = $quiz['document'];
        $doc['questions'][0]['type'] = 'multi_select';
        $doc['questions'][0]['options'][0]['isCorrect'] = true;
        $doc['questions'][0]['options'][] = ['id' => null, 'content' => 'Wrong', 'isCorrect' => false];
        $doc['questions'][1]['type'] = 'short_text';
        $doc['questions'][1]['textAnswers'] = ['Tashkent'];
        $doc['questions'][1]['options'] = [];
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $doc);
        $attempt = $this->startQuiz($quiz);
        $first = $this->submission($attempt, 0); // One of the two correct multi-select choices.
        $text = ['questionId' => $attempt['quiz']['questions'][1]['id'], 'answerCodes' => [], 'textAnswer' => '  TASHKENT  ',
            'saveVer' => 1, 'submitKey' => bin2hex(random_bytes(16)), 'reason' => 'answered', 'startedAt' => $attempt['startedAt']];
        $skip = $this->submission($attempt, 2); $skip['reason'] = 'skipped';
        $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$first, $text, $skip], 'finishReason' => 'completed']);
        $raw = $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->get()->getRowArray();
        $this->assertSame('1.50', \App\Services\Player\ScoringService::decimal(\App\Services\Player\ScoringService::hundredths((string) $raw['score'])));
        $items = \App\Services\Player\AttemptResponses::decode($raw['responses']);
        $this->assertSame(['0.50', '1.00', '0.00'], array_column($items, 'credit'));
        $doc = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $doc['questions'] = [$doc['questions'][2]];
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $doc);
        $results = new \App\Services\TeacherResultsService($this->db, $this->media, new \App\Services\QuizPaperService($this->db, $this->media));
        $report = $results->attempt($quiz['owner'], $attempt['attemptId'], 'UTC');
        $this->assertCount(3, $report['questions']);
        $this->assertSame(['partial', 'correct', 'unanswered'], array_column($report['questions'], 'result'));
        $this->assertSame(array_column($attempt['quiz']['questions'][0]['options'], 'content'), array_column($report['questions'][0]['options'], 'content'));
        $this->assertSame(['A', 'B', 'C'], array_column($report['questions'][0]['options'], 'label'));
        $this->assertSame('TASHKENT', trim($report['questions'][1]['answer']));
        $this->assertSame('1.50', $report['attempt']['score']);
        $this->assertStringNotContainsString('responses', json_encode($report, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('submitHash', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function testScheduledCloseGradesCurrentDraftAndLocksRemainingQuestions(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update(['close_at' => '2000-01-01 00:00:00.000000']);
        $draft = $this->submission($attempt, 0); $draft['submitKey'] = null;
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$draft], 'finishReason' => 'scheduled_close']);
        $this->assertSame('expired', $saved['status']);
        $this->assertTrue($saved['lateSync']);
        $this->assertSame('1.00', $saved['result']['score']);
        $this->assertSame(['attempt_timeout', 'attempt_timeout'], array_column($saved['items'], 'reason'));
        $this->assertSame(['correct', 'unanswered'], array_column($saved['result']['items'], 'result'));
    }

    public function testLateWorkIsAcceptedAndFlagged(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['timeLimitMinutes' => '0.5']));
        $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update(['total_due_at' => '2000-01-01 00:00:00.000000']);
        $this->assertSame('in_progress', $this->player->assessment->load($attempt['attemptId'], $attempt['credential'])['status']);
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$this->submission($attempt, 0), $this->submission($attempt, 1)], 'finishReason' => 'completed']);
        $this->assertTrue($saved['lateSync']);
        $this->assertSame('2.00', $saved['result']['score']);
    }

    public function testTimerFinishLeavesRemainingQuestionsUnanswered(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['timeLimitMinutes' => '0.5']));
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$this->submission($attempt, 0)], 'finishReason' => 'total_timeout']);
        $this->assertSame('expired', $saved['status']);
        $this->assertSame('unanswered', $saved['result']['items'][1]['result']);
        $this->assertSame('1.00', $saved['result']['score']);
    }

    public function testInvalidPublishedEditRollsBackAndDoesNotDisruptStarts(): void
    {
        $quiz = $this->quiz();
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $revision = $document['revision'];
        $document['questions'] = [];
        try {
            $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
            $this->fail('An invalid published edit must not commit.');
        } catch (AuthoringException $error) {
            $this->assertSame('not_publishable', $error->errorCode);
        }
        $server = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $this->assertSame($revision, $server['revision']);
        $this->assertCount(2, $server['questions']);
        $this->assertCount(2, $this->startQuiz($quiz)['quiz']['questions']);
    }

    public function testEditingAndDeletingLiveQuestionsCreatesANewPaperWithoutChangingOldGrading(): void
    {
        $quiz = $this->quiz();
        $old = $this->startQuiz($quiz);
        $quizRow = $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->get()->getRowArray();
        $oldRevision = (int) $this->db->table('quiz_papers')->where('quiz_id', $quizRow['id'])->get()->getRowArray()['revision'];

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['title'] = 'Revised live quiz';
        array_shift($document['questions']);
        $document['questions'][0]['content'] = 'Revised question for future students';
        $document['questions'][0]['options'][0]['isCorrect'] = true;
        $document['questions'][0]['options'][1]['isCorrect'] = false;
        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);

        $this->assertGreaterThan((int) $oldRevision, $saved['revision']);
        $this->assertSame(1, $this->db->table('questions')->where('quiz_id', $quizRow['id'])->countAllResults());
        $oldReloaded = $this->player->assessment->load($old['attemptId'], $old['credential']);
        $this->assertSame('Capital cities', $oldReloaded['quiz']['title']);
        $this->assertCount(2, $oldReloaded['quiz']['questions']);
        $graded = $this->player->assessment->sync($old['attemptId'], $old['credential'], [
            'version' => 1,
            'items' => [$this->submission($old, 0), $this->submission($old, 1)],
            'finishReason' => 'completed',
        ]);
        $this->assertSame('2.00', $graded['result']['score']);

        $new = $this->startQuiz($quiz);
        $this->assertSame('Revised live quiz', $new['quiz']['title']);
        $this->assertCount(1, $new['quiz']['questions']);
        $this->assertSame('Revised question for future students', $new['quiz']['questions'][0]['content']);
        $this->assertSame(2, $this->db->table('quiz_papers')->countAllResults());
    }

    public function testLiveChangesDoNotChangeStartedAttemptPaper(): void
    {
        $quiz = $this->quiz(settings: ['feedback' => 'after_each', 'shuffleQuestions' => true, 'shuffleOptions' => true]);
        $attempt = $this->startQuiz($quiz);
        $doc = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $doc['feedback'] = 'at_end'; $doc['showScore'] = false; $doc['shuffleQuestions'] = false;
        $this->authoring->save($quiz['owner'], $quiz['publicId'], $doc);
        $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'archive');
        $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'trash');
        $loaded = $this->player->assessment->load($attempt['attemptId'], $attempt['credential']);
        $this->assertSame('after_each', $loaded['quiz']['settings']['feedback']);
        $this->assertTrue($loaded['quiz']['settings']['showScore']);
        $this->assertSame($attempt['quiz']['questions'], $loaded['quiz']['questions']);
        $this->expectException(PlayerException::class);
        $this->player->admission->ticket($quiz['share']);
    }

    public function testPasscodeAndIdentityValidationRollback(): void
    {
        $quiz = $this->quiz(settings: ['emailMode' => 'required', 'phoneMode' => 'hidden', 'passcode' => ['action' => 'set', 'value' => 'open-sesame']]);
        $ticket = $this->player->admission->ticket($quiz['share']);
        foreach ([['name' => '', 'email' => 'student@example.test'], ['name' => 'Student', 'email' => 'bad'], ['name' => 'Student', 'email' => 'student@example.test', 'passcode' => 'wrong']] as $identity) {
            try { $this->player->admission->start(['ticket' => $ticket['ticket']] + $identity); $this->fail(); }
            catch (PlayerException) { $this->assertSame(0, $this->db->table('attempts')->countAllResults()); }
        }
        $this->assertFalse($this->authoring->document($quiz['owner'], $quiz['publicId'])['hasStarted']);
        $this->player->admission->start(['ticket' => $ticket['ticket'], 'name' => 'Student', 'email' => 'student@example.test', 'phone' => 'ignored', 'passcode' => 'open-sesame']);
        $this->assertNull($this->db->table('attempts')->get()->getRow('phone'));
    }

    public function testPracticeOnlyCountsDeduplicatedStarts(): void
    {
        $quiz = $this->quiz('practice');
        $ticket = $this->player->admission->ticket($quiz['share']);
        $first = $this->player->admission->start(['ticket' => $ticket['ticket']], '127.0.0.1', 'Not stored');
        $again = $this->player->admission->start(['ticket' => $ticket['ticket']]);
        $this->assertSame($first['startedAt'], $again['startedAt']);
        $this->assertSame('1', (string) $this->db->table('quizzes')->get()->getRow('practice_starts'));
        $this->assertSame(1, $this->db->table('practice_keys')->countAllResults());
        $this->assertSame(0, $this->db->table('attempts')->countAllResults());
        $this->assertFalse($this->db->tableExists('attempt_items'));
        $this->assertSame($first['quiz']['questions'], $this->player->practice->media($first['credential'])['quiz']['questions']);
        $this->expectException(PlayerException::class);
        $this->player->admission->start(['ticket' => $ticket['ticket'], 'name' => 'Not allowed']);
    }

    public function testBearerIsRequiredAndScoresCanBeHidden(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['showScore' => false], count: 1));
        $saved = $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], ['version' => 1, 'items' => [$this->submission($attempt, 0)], 'finishReason' => 'completed']);
        $this->assertArrayNotHasKey('score', $saved['result']);
        $this->assertArrayNotHasKey('points', $saved['result']['items'][0]);
        $this->assertArrayNotHasKey('credit', $saved['result']['items'][0]);
        $this->expectException(PlayerException::class);
        $this->player->assessment->load($attempt['attemptId'], str_repeat('0', 64));
    }

    public function testIntegrityEventsAreOptInAndIdempotent(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['cheatCheck' => true]));
        $payload = ['events' => [['key' => bin2hex(random_bytes(16)), 'type' => 'tab_hidden', 'happenedAt' => $attempt['startedAt']]]];
        $this->player->assessment->events($attempt['attemptId'], $attempt['credential'], $payload);
        $this->player->assessment->events($attempt['attemptId'], $attempt['credential'], $payload);
        $this->assertSame(1, $this->db->table('cheat_events')->countAllResults());
        $this->player->assessment->events($attempt['attemptId'], $attempt['credential'], ['events' => [[
            'key' => bin2hex(random_bytes(16)), 'type' => 'fullscreen_exit', 'happenedAt' => $attempt['startedAt'],
        ]]]);
        $this->assertSame(2, $this->db->table('cheat_events')->countAllResults());
        try {
            $this->player->assessment->events($attempt['attemptId'], $attempt['credential'], ['events' => [[
                'key' => bin2hex(random_bytes(16)), 'type' => 'window_blur', 'happenedAt' => $attempt['startedAt'],
            ]]]);
            $this->fail('Removed integrity events must be rejected.');
        } catch (PlayerException $exception) {
            $this->assertSame('invalid_progress', $exception->errorCode);
        }
        $this->assertSame('in_progress', $this->db->table('attempts')->get()->getRow('status'));
    }

    public function testOversizedVersionsAndNestedForeignQuestionsAreRejectedWithoutWrites(): void
    {
        $attempt = $this->startQuiz($this->quiz());
        $other = $this->startQuiz($this->quiz());
        $valid = $this->submission($attempt, 0);
        foreach ([['version' => 4294967296, 'items' => [$valid]],
            ['version' => 1, 'items' => [array_replace($valid, ['saveVer' => 4294967296])]],
            ['version' => 1, 'items' => [$this->submission($other, 0)]]] as $payload) {
            try { $this->player->assessment->sync($attempt['attemptId'], $attempt['credential'], $payload); $this->fail('Invalid progress accepted'); }
            catch (PlayerException $error) { $this->assertSame('invalid_progress', $error->errorCode); }
        }
        $this->assertSame(1, $this->player->assessment->load($attempt['attemptId'], $attempt['credential'])['version']);
    }

    public function testEveryQuizStateAndScheduleBlocksNewStarts(): void
    {
        $quiz = $this->quiz();
        foreach ([['status' => 'draft'], ['status' => 'closed'], ['status' => 'archived'],
            ['deleted_at' => '2026-01-01 00:00:00.000000'], ['opens_at' => '2099-01-01 00:00:00.000000'],
            ['closes_at' => '2000-01-01 00:00:00.000000']] as $change) {
            $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->update(array_replace(['status' => 'published', 'deleted_at' => null, 'opens_at' => null, 'closes_at' => null], $change));
            try { $this->player->admission->ticket($quiz['share']); $this->fail('Unavailable quiz admitted a student'); }
            catch (PlayerException $error) { $this->assertContains($error->status, [404, 409]); }
        }
        $this->assertSame(0, $this->db->table('attempts')->countAllResults());
    }
}
