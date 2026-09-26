<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

final class QuizController extends BaseController
{
    public function show(string $shareToken): string
    {
        $quiz = service('quizAuthoring')->publicSummary($shareToken);

        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->privateResponse();
        return view('public/quiz', ['quiz' => $quiz, 'title' => $quiz['title'] . ' — EduTest', 'shareToken' => $shareToken, 'page' => 'intro']);
    }

    public function play(string $shareToken): string
    {
        return $this->shell($shareToken, 'play');
    }

    public function results(string $shareToken): string
    {
        return $this->shell($shareToken, 'results');
    }

    private function shell(string $shareToken, string $page): string
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $shareToken)) throw PageNotFoundException::forPageNotFound();
        // Existing bearers may finish after the teacher closes or archives the quiz.
        $this->privateResponse();
        return view('student/player', ['title' => lang('Player.ui.quizPlayer') . ' — EduTest', 'shareToken' => $shareToken, 'page' => $page]);
    }

    private function privateResponse(): void
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')->setHeader('Referrer-Policy', 'no-referrer')->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
