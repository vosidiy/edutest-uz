<?php

declare(strict_types=1);

use App\Services\Player\AttemptResponses;
use CodeIgniter\Test\CIUnitTestCase;

final class AttemptResponsesTest extends CIUnitTestCase
{
    public function testCompactPendingItemsAndLargeIdsRoundTripWithoutDuplicatingPaper(): void
    {
        $questions = [
            ['id' => '9007199254740993', 'content' => 'Not stored', 'options' => [['code' => 'b'], ['code' => 'a']]],
            ['id' => '18446744073709551615', 'options' => []],
        ];
        $stored = AttemptResponses::initialize($questions, '2026-01-01 00:00:00.000000');
        $doc = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['q' => '18446744073709551615', 'o' => []], $doc['items'][1]);
        $this->assertStringNotContainsString('Not stored', $stored);
        $rows = AttemptResponses::decode($stored);
        $this->assertSame('9007199254740993', $rows[0]['question_id']);
        $this->assertSame('["b","a"]', $rows[0]['choice_order']);
        $this->assertSame(2, $rows[1]['pos']);
        $this->assertSame('pending', $rows[1]['status']);
        $this->assertSame(0, $rows[1]['save_ver']);
        $this->assertNull($rows[1]['credit']);
        $this->assertSame($stored, AttemptResponses::encode($rows));
    }

    public function testSubmittedAnswersAndHexHashesRoundTripExactly(): void
    {
        $row = ['question_id' => '12', 'choice_order' => '["a","b"]', 'answer_codes' => '["b"]',
            'status' => 'locked', 'started_at' => '2026-01-01 00:00:00.123456',
            'locked_at' => '2026-01-01 00:00:20.123456', 'saved_at' => '2026-01-01 00:00:20.123456',
            'lock_reason' => 'answered', 'save_ver' => 3, 'submit_key' => str_repeat('a', 32),
            'submit_hash' => hash('sha256', 'answer'), 'result' => 'partial', 'credit' => '0.67'];
        $actual = AttemptResponses::decode(AttemptResponses::encode([$row]))[0];
        foreach ($row as $key => $value) $this->assertSame($value, $actual[$key]);
    }

    public function testInvalidDocumentsFailClosed(): void
    {
        $base = ['schemaVersion' => 1, 'items' => [['q' => '1', 'o' => ['a', 'b']]]];
        $documents = [[], ['schemaVersion' => 2, 'items' => []], ['schemaVersion' => 1, 'items' => 'bad'],
            $base + ['unknown' => true], ['schemaVersion' => 1, 'items' => [$base['items'][0], $base['items'][0]]]];
        foreach ([['q' => 1], ['q' => '0'], ['o' => ['a', 'a']], ['o' => [1]],
            ['a' => ['foreign']], ['a' => ['a', 'a']], ['a' => null], ['s' => null], ['v' => null], ['s' => 'unknown'],
            ['s' => 'active'], ['v' => -1], ['v' => '1'], ['v' => 4294967296],
            ['t' => []], ['t' => str_repeat('a', 501)], ['b' => 'yesterday'],
            ['k' => str_repeat('a', 32)], ['h' => str_repeat('b', 64)],
            ['s' => 'locked', 'c' => '1.01'], ['c' => '0.00'], ['g' => 'correct'],
            ['unexpected' => true]] as $change) {
            $documents[] = ['schemaVersion' => 1, 'items' => [array_replace($base['items'][0], $change)]];
        }
        foreach ($documents as $document) {
            try { AttemptResponses::decode($document); $this->fail('Accepted invalid response document'); }
            catch (RuntimeException $exception) { $this->assertSame('The stored assessment responses are invalid.', $exception->getMessage()); }
        }
    }
}
