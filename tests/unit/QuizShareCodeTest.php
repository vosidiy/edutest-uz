<?php

declare(strict_types=1);

use App\Services\QuizShareCode;
use CodeIgniter\Test\CIUnitTestCase;

final class QuizShareCodeTest extends CIUnitTestCase
{
    public function testGeneratedCodesAreNineDigitsAndLeadingZeroesAreValid(): void
    {
        for ($index = 0; $index < 25; $index++) {
            $this->assertMatchesRegularExpression('/^[0-9]{9}$/D', QuizShareCode::generate());
        }

        $this->assertTrue(QuizShareCode::isValid('000000001'));
        $this->assertTrue(QuizShareCode::isValid(str_repeat('a', 64)));
        $this->assertFalse(QuizShareCode::isValid('12345678'));
        $this->assertFalse(QuizShareCode::isValid(str_repeat('g', 64)));
    }
}
