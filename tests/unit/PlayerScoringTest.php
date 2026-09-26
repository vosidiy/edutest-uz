<?php

declare(strict_types=1);

use App\Services\Player\ScoringService;
use CodeIgniter\Test\CIUnitTestCase;

final class PlayerScoringTest extends CIUnitTestCase
{
    public function testSharedScoringFixtures(): void
    {
        $scorer = new ScoringService();
        foreach (json_decode(file_get_contents(ROOTPATH . 'tests/fixtures/player-scoring.json'), true, 512, JSON_THROW_ON_ERROR) as $case) {
            $this->assertSame($case['expected'], $scorer->grade($case['question'], $case['answer']), $case['name']);
        }
    }

    public function testRejectsDuplicateChoiceCodes(): void
    {
        $question = json_decode(file_get_contents(ROOTPATH . 'tests/fixtures/player-scoring.json'), true)[0]['question'];
        $this->expectException(\App\Exceptions\PlayerException::class);
        (new ScoringService())->grade($question, ['answerCodes' => ['right', 'right']]);
    }
}
