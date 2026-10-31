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
        self::assertSame(120, $document['timeLimitMinutes']);
        self::assertFalse($document['askEmail']);
        self::assertFalse($document['askPhone']);
        self::assertNull($document['drawCount']);
        self::assertSame('list', $document['questions'][0]['optionsLayout']);
        self::assertArrayNotHasKey('mode', $document);
        self::assertArrayNotHasKey('revision', $document);
        self::assertArrayNotHasKey('version', $document);
        self::assertArrayNotHasKey('listed', $document);
    }

    public function testTimerNormalizationRoundsValidValuesAndDefaultsInvalidValues(): void
    {
        $quiz = $this->quiz(count: 1);
        foreach ([[1, 1], [60, 60], [119, 119], [120, 120], [1.4, 1], [1.5, 2], ['60.4', 60], [120.4, 120], [0.5, 1]] as [$input, $expected]) {
            $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
            $document['timeLimitMinutes'] = $input;
            $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
            self::assertSame($expected, $saved['timeLimitMinutes']);
            self::assertSame($expected * 60, $saved['timeLimitSec']);
        }

        foreach ([0, -1, 120.5, 121, '', 'letters', '0x10', null, [], new \stdClass(), '1e9999', NAN, INF] as $input) {
            $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
            $document['timeLimitMinutes'] = $input;
            $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
            self::assertSame(120, $saved['timeLimitMinutes']);
            self::assertSame(7200, $saved['timeLimitSec']);
        }

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        unset($document['timeLimitMinutes']);
        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        self::assertSame(120, $saved['timeLimitMinutes']);
        self::assertSame(7200, $saved['timeLimitSec']);
    }

    public function testStatusChangesAreAtomicWithManualSave(): void
    {
        $quiz = $this->quiz(count: 1);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $firstPublishedAt = $document['publishedAt'];
        $document['status'] = 'draft';
        $document['title'] = 'Saved as draft';
        $draft = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        self::assertSame('draft', $draft['status']);
        self::assertSame('Saved as draft', $draft['title']);
        self::assertSame($firstPublishedAt, $draft['publishedAt']);

        $draft['status'] = 'published';
        $active = $this->authoring->save($quiz['owner'], $quiz['publicId'], $draft);
        self::assertSame('published', $active['status']);
        self::assertNotNull($active['publishedAt']);
    }

    public function testActiveSaveWithNoQuestionsRollsBackAllChanges(): void
    {
        $quiz = $this->quiz(count: 1);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['status'] = 'draft';
        $draft = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);

        $draft['status'] = 'published';
        $draft['title'] = 'Must not persist';
        $draft['questions'] = [];
        try {
            $this->authoring->save($quiz['owner'], $quiz['publicId'], $draft);
            self::fail('An Active quiz must contain a question.');
        } catch (AuthoringException $error) {
            self::assertSame('not_publishable', $error->errorCode);
            self::assertSame('Add at least one question before making this quiz Active.', $error->getMessage());
        }

        $stored = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        self::assertSame('Capital cities', $stored['title']);
        self::assertSame('draft', $stored['status']);
        self::assertCount(1, $stored['questions']);

        $stored['questions'] = [];
        self::assertSame([], $this->authoring->save($quiz['owner'], $quiz['publicId'], $stored)['questions']);
    }

    public function testBlankQuestionAndChoicesAreNormalizedAndRunnable(): void
    {
        $quiz = $this->quiz(count: 1);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['questions'][0]['content'] = '';
        $document['questions'][0]['options'] = [[
            'id' => $document['questions'][0]['options'][0]['id'],
            'content' => '   ',
            'isCorrect' => false,
        ]];

        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        self::assertSame('', $saved['questions'][0]['content']);
        self::assertSame(['Option 1', 'Option 2'], array_column($saved['questions'][0]['options'], 'content'));
        self::assertSame([true, false], array_column($saved['questions'][0]['options'], 'isCorrect'));

        $saved['questions'][0]['options'][] = ['id' => null, 'content' => 'Third', 'isCorrect' => true];
        $saved['questions'][0]['options'][1]['isCorrect'] = true;
        $repaired = $this->authoring->save($quiz['owner'], $quiz['publicId'], $saved);
        self::assertSame([true, false, false], array_column($repaired['questions'][0]['options'], 'isCorrect'));

        $attempt = $this->startQuiz($quiz);
        self::assertSame('', $attempt['quiz']['questions'][0]['content']);
        self::assertSame(['Option 1', 'Option 2', 'Third'], array_column($attempt['quiz']['questions'][0]['options'], 'content'));
    }

    public function testDrawCountRoundsValidValuesAndUsesWholePoolForInvalidValues(): void
    {
        $quiz = $this->quiz(count: 5);
        foreach ([[1, 1], [2.4, 2], [2.5, 3], ['4.4', 4], [0.5, 1], [5, 5]] as [$input, $expected]) {
            $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
            $document['drawCount'] = $input;
            self::assertSame($expected, $this->authoring->save($quiz['owner'], $quiz['publicId'], $document)['drawCount']);
        }

        foreach ([0, -1, 5.5, 6, '', 'letters', '0x2', null, [], new \stdClass(), '1e9999', NAN, INF] as $input) {
            $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
            $document['drawCount'] = $input;
            self::assertNull($this->authoring->save($quiz['owner'], $quiz['publicId'], $document)['drawCount']);
        }

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        unset($document['drawCount']);
        self::assertNull($this->authoring->save($quiz['owner'], $quiz['publicId'], $document)['drawCount']);

        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $document['status'] = 'draft';
        $document['questions'] = [];
        $document['drawCount'] = 1;
        self::assertNull($this->authoring->save($quiz['owner'], $quiz['publicId'], $document)['drawCount']);
    }

    public function testMissingChoicesArePaddedWithAValidDefaultAnswer(): void
    {
        $quiz = $this->quiz(count: 1);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        unset($document['questions'][0]['options']);

        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        self::assertSame(['Option 1', 'Option 2'], array_column($saved['questions'][0]['options'], 'content'));
        self::assertSame([true, false], array_column($saved['questions'][0]['options'], 'isCorrect'));
    }

    public function testListAndGridLayoutsUseLastWriteWins(): void
    {
        $quiz = $this->quiz(count: 2);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);
        $stale = $document;
        $document['questions'][0]['optionsLayout'] = 'grid';
        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        self::assertSame('grid', $saved['questions'][0]['optionsLayout']);
        $stale['title'] = 'Last writer';
        $replaced = $this->authoring->save($quiz['owner'], $quiz['publicId'], $stale);
        self::assertSame('Last writer', $replaced['title']);
        self::assertSame('list', $replaced['questions'][0]['optionsLayout']);
    }

    public function testUnchangedExplicitSaveRefreshesUpdatedAt(): void
    {
        $quiz = $this->quiz(count: 1);
        $row = $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->get()->getRowArray();
        $this->db->table('quizzes')->where('id', $row['id'])->update(['updated_at' => '2000-01-01 00:00:00.000000']);
        $document = $this->authoring->document($quiz['owner'], $quiz['publicId']);

        $saved = $this->authoring->save($quiz['owner'], $quiz['publicId'], $document);
        $stored = $this->db->table('quizzes')->where('id', $row['id'])->get()->getRowArray();

        self::assertNotSame('2000-01-01 00:00:00.000000', $stored['updated_at']);
        self::assertNotSame($document['updatedAt'], $saved['updatedAt']);
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
