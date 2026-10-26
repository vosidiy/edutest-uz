<?php

declare(strict_types=1);

namespace Tests\App\Services;

use Tests\Support\PlayerTestCase;

final class AttemptSnapshotServiceTest extends PlayerTestCase
{
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
