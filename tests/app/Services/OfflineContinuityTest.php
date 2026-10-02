<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\PlayerException;
use App\Services\Player\PlayerStore;
use App\Services\TeacherResultsService;
use App\Services\QuizPaperService;
use Tests\Support\PlayerTestCase;

final class OfflineContinuityTest extends PlayerTestCase
{
    private function age(array $attempt, int $seconds = 120, int $limit = 30): array
    {
        $start = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify("-{$seconds} seconds")->format('Y-m-d H:i:s.u');
        $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->update([
            'started_at' => $start, 'last_activity_at' => $start, 'client_activity_at' => $start,
            'expires_at' => PlayerStore::due($start, $limit), 'deadline_reason' => $limit === 28800 ? 'stale_timeout' : 'timer_expired',
        ]);
        return [$start, fn (int $offset): string => PlayerStore::iso(PlayerStore::due($start, $offset))];
    }

    private function error(string $code, callable $fn): void
    {
        try { $fn(); self::fail('Expected ' . $code); }
        catch (PlayerException $error) { self::assertSame($code, $error->errorCode); }
    }

    public function testLateCompletionRecoversAbandonedResultsAndKeepsReceiptAndOccurrenceSeparate(): void
    {
        $quiz = $this->quiz(settings: ['timeLimitMinutes' => '0.5']);
        $a = $this->startQuiz($quiz); [$start, $at] = $this->age($a);
        $this->assertSame('abandoned', $this->player->assessment->load($a['attemptId'], $a['credential'])['status']);
        foreach ([0, 1] as $index) {
            $input = $this->confirmation($this->submission($a, $index), $at(5 + $index));
            $ack = $this->player->assessment->answer($a['attemptId'], $a['quiz']['questions'][$index]['id'], $a['credential'], $input);
            $this->assertArrayNotHasKey('quiz', $ack); $this->assertArrayNotHasKey('items', $ack);
            $this->assertTrue($ack['lateSync']); $this->assertSame('abandoned', $ack['status']);
            $this->assertLessThan(1000, strlen(json_encode($ack)));
        }
        $body = $this->finishBody($a, at: $at(10));
        $done = $this->player->assessment->finish($a['attemptId'], $a['credential'], $body);
        $this->assertSame('completed', $done['status']); $this->assertSame('2', $done['result']['score']);
        $this->assertTrue($done['result']['confirmed']);
        $this->assertSame($done, $this->player->assessment->finish($a['attemptId'], $a['credential'], $body));
        $reporting = new TeacherResultsService($this->db, $this->media, new QuizPaperService($this->db, $this->media));
        $report = $reporting->attempt($quiz['owner'], $a['attemptId'], 'UTC');
        $this->assertTrue($report['attempt']['lateSync']);
        $this->assertNotNull($report['questions'][0]['clientAnsweredAt']);
        $rows = iterator_to_array($reporting->export($quiz['owner'], $quiz['publicId'], [], 'UTC')['rows']);
        $this->assertSame('yes', $rows[0]['lateSync']);
        $answer = $this->db->table('attempt_answers')->orderBy('pos')->get()->getRowArray();
        $this->assertLessThan($answer['answered_at'], $answer['client_answered_at']);
    }

    public function testTimeoutFinishAcceptsConfirmedPrefixAndRejectsChangedFinalization(): void
    {
        $a = $this->startQuiz($this->quiz(settings: ['timeLimitMinutes' => '0.5'], count: 3));
        [, $at] = $this->age($a);
        $body = $this->finishBody($a, 'timer_expired', $at(30), $at(8), 1);
        $this->error('incomplete_sync', fn () => $this->player->assessment->finish($a['attemptId'], $a['credential'], $body));
        $input = $this->confirmation($this->submission($a, 0), $at(8));
        $this->player->assessment->answer($a['attemptId'], $a['quiz']['questions'][0]['id'], $a['credential'], $input);
        $done = $this->player->assessment->finish($a['attemptId'], $a['credential'], $body);
        $this->assertSame('completed', $done['status']); $this->assertSame('timer_expired', $done['finishReason']);
        $this->assertSame('1', $done['result']['score']);
        $this->assertSame(['answered','not_reached','not_reached'], array_column($this->db->table('attempt_answers')->orderBy('pos')->get()->getResultArray(), 'status'));
        $this->error('finish_conflict', fn () => $this->player->assessment->finish($a['attemptId'], $a['credential'], array_replace($body, ['clientFinishedAt' => $at(29)])));
        $retry = $this->player->assessment->answer($a['attemptId'], $a['quiz']['questions'][0]['id'], $a['credential'], $input);
        $this->assertSame('completed', $retry['status']);
        $this->error('attempt_finalized', fn () => $this->player->assessment->answer($a['attemptId'], $a['quiz']['questions'][1]['id'], $a['credential'], $this->confirmation($this->submission($a, 1), $at(9))));
    }

    public function testInvalidLateAnswerCommitsLazyAbandonmentButNoAnswerWrite(): void
    {
        $a = $this->startQuiz($this->quiz(settings: ['timeLimitMinutes' => '0.5'])); [, $at] = $this->age($a);
        $body = $this->confirmation($this->submission($a, 0), $at(31));
        $this->error('invalid_timing', fn () => $this->player->assessment->answer($a['attemptId'], $a['quiz']['questions'][0]['id'], $a['credential'], $body));
        $this->assertSame('abandoned', $this->db->table('attempts')->get()->getRow('status'));
        $this->assertSame(0, $this->db->table('attempt_answers')->where('status', 'answered')->countAllResults());
    }

    public function testUntimedLocalActivityRevivesOnlyItsCapturedInactivityDeadline(): void
    {
        $a = $this->startQuiz($this->quiz()); [$start, $at] = $this->age($a, 43200, 28800);
        $old = $this->player->assessment->load($a['attemptId'], $a['credential']);
        $this->assertSame('abandoned', $old['status']);
        $ack = $this->player->assessment->activity($a['attemptId'], $a['credential'], ['clientActivityAt' => $at(40000)]);
        $this->assertSame('in_progress', $ack['status']); $this->assertTrue($ack['lateSync']);
        $this->assertSame($at(68800), $ack['expiresAt']);
        $again = $this->player->assessment->activity($a['attemptId'], $a['credential'], ['clientActivityAt' => $at(40000)]);
        $this->assertSame($ack, $again);
        $loaded = $this->player->assessment->load($a['attemptId'], $a['credential']);
        $this->assertSame($ack['expiresAt'], $loaded['expiresAt']);
        $this->assertNull($this->db->table('attempts')->get()->getRow('finished_at'));
    }

    public function testUntimedStaleFinishDoesNotCountUnsubmittedAnswers(): void
    {
        $a = $this->startQuiz($this->quiz()); [, $at] = $this->age($a, 43200, 28800);
        $done = $this->player->assessment->finish($a['attemptId'], $a['credential'], $this->finishBody($a, 'stale_timeout', $at(28800), $at(0), 0));
        $this->assertSame('completed', $done['status']); $this->assertSame('stale_timeout', $done['finishReason']);
        $this->assertSame('0', $done['result']['score']);
    }

    public function testScheduledCloseCapsOfflineActivityAndAllowsLateUpload(): void
    {
        $closeDate = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 hour')->setTime((int) gmdate('H', time() + 3600), (int) gmdate('i'));
        $close = $closeDate->format('Y-m-d\TH:i:s.u\Z');
        $quiz = $this->quiz(settings: ['closesAtLocal' => $closeDate->setTimezone(new \DateTimeZone('Asia/Tashkent'))->format('Y-m-d\TH:i')]);
        $a = $this->startQuiz($quiz);
        $ack = $this->player->assessment->activity($a['attemptId'], $a['credential'], ['clientActivityAt' => PlayerStore::iso(PlayerStore::now())]);
        $this->assertSame('scheduled_close', $ack['deadlineReason']); $this->assertSame($close, $ack['expiresAt']);
        $this->error('invalid_timing', fn () => $this->player->assessment->activity($a['attemptId'], $a['credential'], ['clientActivityAt' => $close]));
    }

    public function testIndependentConfirmationsRejectChangedAnswersAndChangedOccurrence(): void
    {
        $a = $this->startQuiz($this->quiz());
        $second = $this->confirmation($this->submission($a, 1));
        $id = $a['quiz']['questions'][1]['id'];
        $saved = $this->player->assessment->answer($a['attemptId'], $id, $a['credential'], $second);
        $this->assertSame($saved, $this->player->assessment->answer($a['attemptId'], $id, $a['credential'], $second));
        $this->error('answer_locked', fn () => $this->player->assessment->answer($a['attemptId'], $id, $a['credential'], array_replace($second, ['answerCodes' => [$a['quiz']['questions'][1]['options'][0]['code']]])));
        $this->error('incomplete_sync', fn () => $this->player->assessment->finish($a['attemptId'], $a['credential'], $this->finishBody($a, count: 2)));
    }

    public function testHiddenResultIsReceiptOnlyAndDelayedIntegrityRemainsInformational(): void
    {
        $a = $this->startQuiz($this->quiz(settings: ['showScore' => false, 'showAnswers' => false, 'showExplain' => false, 'cheatCheck' => true, 'timeLimitMinutes' => '0.5']));
        [, $at] = $this->age($a);
        $done = $this->player->assessment->finish($a['attemptId'], $a['credential'], $this->finishBody($a, 'timer_expired', $at(30), $at(0), 0));
        $this->assertArrayNotHasKey('score', $done['result']); $this->assertSame([], $done['result']['items']);
        $events = ['events' => [['key' => str_repeat('a',32), 'type' => 'tab_hidden', 'happenedAt' => $at(10), 'durationMs' => null]]];
        $this->player->assessment->events($a['attemptId'], $a['credential'], $events);
        $this->player->assessment->events($a['attemptId'], $a['credential'], $events);
        $this->assertSame(1, $this->db->table('cheat_events')->countAllResults());
        $this->assertSame('completed', $this->db->table('attempts')->get()->getRow('status'));
    }

    public function testQuitFinalizesZeroOrPartialConfirmedPrefixesAndIsRetrySafe(): void
    {
        $quiz = $this->quiz(count: 3);
        $empty = $this->startQuiz($quiz, ['name' => '<Alex>', 'email' => 'alex@example.test']);
        $this->assertSame(['name' => '<Alex>', 'email' => 'alex@example.test'], $empty['student']);
        $emptyBody = $this->finishBody($empty, 'quit', count: 0);
        $emptyDone = $this->player->assessment->finish($empty['attemptId'], $empty['credential'], $emptyBody);
        $this->assertSame('completed', $emptyDone['status']);
        $this->assertSame('quit', $emptyDone['finishReason']);
        $this->assertSame('0', $emptyDone['result']['score']);
        $this->assertSame($emptyDone, $this->player->assessment->finish($empty['attemptId'], $empty['credential'], $emptyBody));
        $this->error('finish_conflict', fn () => $this->player->assessment->finish(
            $empty['attemptId'],
            $empty['credential'],
            array_replace($emptyBody, ['finishReason' => 'completed']),
        ));

        $partial = $this->startQuiz($quiz);
        $answer = $this->confirmation($this->submission($partial, 0));
        $this->player->assessment->answer($partial['attemptId'], $partial['quiz']['questions'][0]['id'], $partial['credential'], $answer);
        $partialDone = $this->player->assessment->finish($partial['attemptId'], $partial['credential'], $this->finishBody($partial, 'quit', count: 1));
        $this->assertSame('1', $partialDone['result']['score']);
        $attemptId = $this->db->table('attempts')->select('id')->where('public_id', $partial['attemptId'])->get()->getRow('id');
        $this->assertSame(
            ['answered', 'not_reached', 'not_reached'],
            array_column($this->db->table('attempt_answers')->where('attempt_id', $attemptId)->orderBy('pos')->get()->getResultArray(), 'status'),
        );
    }

    public function testDelayedQuitCanReconcileProvisionalAbandonment(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['timeLimitMinutes' => '0.5']));
        [, $at] = $this->age($attempt);
        $this->assertSame('abandoned', $this->player->assessment->load($attempt['attemptId'], $attempt['credential'])['status']);
        $answer = $this->confirmation($this->submission($attempt, 0), $at(5));
        $this->player->assessment->answer($attempt['attemptId'], $attempt['quiz']['questions'][0]['id'], $attempt['credential'], $answer);
        $done = $this->player->assessment->finish($attempt['attemptId'], $attempt['credential'], $this->finishBody($attempt, 'quit', $at(10), $at(10), 1));
        $this->assertSame('completed', $done['status']);
        $this->assertSame('quit', $done['finishReason']);
        $this->assertTrue($done['lateSync']);
    }
}
