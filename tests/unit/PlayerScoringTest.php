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
            $code = $scorer->validateCode($case['question'], $case['answer']['answerCode']);
            $expected = $case['expected'];
            $actual = [
                'result' => $code === null ? 'skipped' : ($scorer->isCorrect($case['question'], $code) ? 'correct' : 'wrong'),
                'isCorrect' => $scorer->isCorrect($case['question'], $code),
            ];
            $this->assertSame($expected, $actual, $case['name']);
        }
    }

    public function testRejectsForeignChoiceCode(): void
    {
        $question = json_decode(file_get_contents(ROOTPATH . 'tests/fixtures/player-scoring.json'), true)[0]['question'];
        $this->expectException(\App\Exceptions\PlayerException::class);
        (new ScoringService())->validateCode($question, 'foreign');
    }
}
