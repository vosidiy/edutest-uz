<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\AuthoringException;
use Tests\Support\PlayerTestCase;

final class QuizAuthoringServiceTest extends PlayerTestCase
{
    public function testNewQuizUsesMvpDefaults(): void
    {
        $quiz = $this->quiz(count: 1);
        $document = $quiz['document'];
        self::assertSame('published', $document['status']);
        self::assertSame(7200, $document['timeLimitSec']);
        self::assertFalse($document['askEmail']);
        self::assertFalse($document['askPhone']);
        self::assertNull($document['drawCount']);
        self::assertSame('list', $document['questions'][0]['optionsLayout']);
        self::assertArrayNotHasKey('mode', $document);
        self::assertArrayNotHasKey('revision', $document);
    }

    public function testTimerValidationCoversThirtyToSevenThousandTwoHundredSeconds(): void
    {
        $quiz = $this->quiz(count: 1);
        $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'unpublish');
        foreach ([0, 29, 31, 7230] as $invalid) {
            $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
            $document['timeLimitSec'] = $invalid;
            $this->expectAuthoringError('validation_failed', fn () => $this->authoring->save($quiz['owner'], $quiz['publicId'], $document));
        }
        foreach ([30, 60, 7170, 7200] as $valid) {
            $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
            $document['timeLimitSec'] = $valid;
            self::assertSame($valid, $this->authoring->save($quiz['owner'], $quiz['publicId'], $document)['timeLimitSec']);
        }
    }

    public function testPublishUnpublishAndPublishedSaveSafety(): void
    {
        $quiz = $this->quiz(count: 2);
        self::assertSame('draft', $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'unpublish')['quiz']['status']);
        self::assertSame('published', $this->authoring->transition($quiz['owner'], $quiz['publicId'], 'publish')['quiz']['status']);

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['questions'][0]['options'][1]['isCorrect'] = false;
        $this->expectAuthoringError('not_publishable', fn () => $this->authoring->save($quiz['owner'], $quiz['publicId'], $document));
        self::assertTrue($this->authoring->document($quiz['owner'], $quiz['publicId'])['questions'][0]['options'][1]['isCorrect']);
    }

    public function testDrawCountCannotExceedPublishedQuestionPool(): void
    {
        $quiz = $this->quiz(count: 2);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['drawCount'] = 3;
        $this->expectAuthoringError('not_publishable', fn () => $this->authoring->save($quiz['owner'], $quiz['publicId'], $document));
    }

    public function testListAndGridLayoutsAndOptimisticLockingPersist(): void
    {
        $quiz = $this->quiz(count: 2);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $stale = $document;
        $document['questions'][0]['optionsLayout'] = 'grid';
        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        self::assertSame('grid', $saved['questions'][0]['optionsLayout']);
        $this->expectAuthoringError('version_conflict', fn () => $this->authoring->save($quiz['owner'], $quiz['publicId'], $stale));
    }

    public function testQuestionAndOptionReorderingAvoidsTransientUniquePositionCollisions(): void
    {
        $quiz = $this->quiz(count: 2);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $firstQuestionId = $document['questions'][0]['id'];
        $firstOptionCodes = array_column($document['questions'][0]['options'], 'code');
        $document['questions'] = array_reverse($document['questions']);
        $document['questions'][1]['options'] = array_reverse($document['questions'][1]['options']);

        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);

        self::assertSame($firstQuestionId, $saved['questions'][1]['id']);
        self::assertSame(array_reverse($firstOptionCodes), array_column($saved['questions'][1]['options'], 'code'));
    }

    private function expectAuthoringError(string $code, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$code}.");
        } catch (AuthoringException $error) {
            self::assertSame($code, $error->errorCode);
        }
    }
}
