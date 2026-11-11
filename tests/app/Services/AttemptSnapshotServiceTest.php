<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Services\AttemptSnapshotService;
use Tests\Support\PlayerTestCase;

final class AttemptSnapshotServiceTest extends PlayerTestCase
{
    public function testSnapshotUsesInitialCombinedAnswerVisibilityContract(): void
    {
        $attempt = $this->startQuiz($this->quiz(settings: ['showAnswers' => true], count: 1));

        self::assertSame(1, $attempt['quiz']['schemaVersion']);
        self::assertTrue($attempt['quiz']['quiz']['showAnswers']);
        self::assertArrayNotHasKey('showScore', $attempt['quiz']['quiz']);
        self::assertArrayNotHasKey('showExplain', $attempt['quiz']['quiz']);
        self::assertSame('Paris is the capital of France.', $attempt['quiz']['questions'][0]['explanation']);
    }

    public function testDecoderRejectsUnsupportedSnapshotVersions(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AttemptSnapshotService($this->db, $this->media))->decode([
            'schemaVersion' => 2,
            'quiz' => [],
            'questions' => [],
        ]);
    }

    public function testRandomDrawReturnsSubsetToAuthoredOrderWhenShuffleIsOff(): void
    {
        $quiz = $this->quiz(settings: ['drawCount' => 2], count: 5);
        $authored = array_column($quiz['document']['questions'], 'id');
        $positions = array_flip($authored);
        $signatures = [];

        for ($run = 0; $run < 20; $run++) {
            $attempt = $this->startQuiz($quiz);
            $ids = array_column($attempt['quiz']['questions'], 'id');
            self::assertCount(2, $ids);
            self::assertLessThan($positions[$ids[1]], $positions[$ids[0]]);
            $signatures[implode(',', $ids)] = true;
        }
        self::assertGreaterThan(1, count($signatures));
    }

    public function testQuestionAndOptionShuffleAreCapturedPerAttempt(): void
    {
        $quiz = $this->quiz(settings: ['shuffleQuestions' => true, 'shuffleOptions' => true], count: 4);
        $questionOrders = [];
        $optionOrders = [];

        for ($run = 0; $run < 20; $run++) {
            $attempt = $this->startQuiz($quiz);
            $questionOrders[implode(',', array_column($attempt['quiz']['questions'], 'id'))] = true;
            foreach ($attempt['quiz']['questions'] as $question) {
                $optionOrders[$question['id']][implode(',', array_column($question['options'], 'code'))] = true;
            }
        }

        self::assertGreaterThan(1, count($questionOrders));
        self::assertNotEmpty(array_filter($optionOrders, static fn (array $orders): bool => count($orders) > 1));
    }
}
