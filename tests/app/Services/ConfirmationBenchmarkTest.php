<?php

declare(strict_types=1);

namespace Tests\App\Services;

use Tests\Support\PlayerTestCase;

/** Opt-in comparison of current acknowledgement versus the previous full-state response shape. */
final class ConfirmationBenchmarkTest extends PlayerTestCase
{
    public function testConfirmationPayloadAndProjectionCost(): void
    {
        if (! getenv('EDUTEST_TEST_MYSQL_SOCKET') || ! getenv('EDUTEST_CONFIRMATION_BENCHMARK')) {
            self::markTestSkipped('Requires isolated MySQL and explicit benchmark opt-in.');
        }
        $measurements = [];
        foreach ([20, 100, 500] as $count) {
            $quiz = $this->quiz(count: $count);
            $attempt = $this->startQuiz($quiz);
            $confirmMs = $projectionMs = [];
            for ($index = 0; $index < 10; $index++) {
                $input = $this->confirmation($this->submission($attempt, $index));
                $start = hrtime(true);
                $ack = $this->player->assessment->answer($attempt['attemptId'], $attempt['items'][$index]['questionId'], $attempt['credential'], $input);
                $confirmMs[] = (hrtime(true) - $start) / 1000000;
                $start = hrtime(true);
                $full = $this->player->assessment->load($attempt['attemptId'], $attempt['credential']);
                $projectionMs[] = (hrtime(true) - $start) / 1000000;
            }
            $envelope = static fn (array $value): int => strlen(json_encode(['data' => $value, 'meta' => ['timestamp' => '2026-10-01T00:00:00.000000Z']], JSON_THROW_ON_ERROR));
            sort($confirmMs); sort($projectionMs);
            $measurements[] = ['questions' => $count, 'ackBytes' => $envelope($ack), 'fullStateBytes' => $envelope($full),
                'confirmationMedianMs' => round(($confirmMs[4] + $confirmMs[5]) / 2, 3),
                'additionalFullLoadMedianMs' => round(($projectionMs[4] + $projectionMs[5]) / 2, 3)];
            self::assertArrayNotHasKey('quiz', $ack);
            self::assertLessThan($envelope($full), $envelope($ack));
        }
        fwrite(STDOUT, "\nConfirmation benchmark (10 new answers per size, no-media fixture):\n" . json_encode($measurements, JSON_PRETTY_PRINT) . "\n");
    }
}
