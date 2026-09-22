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

        return view('public/quiz', ['quiz' => $quiz, 'title' => $quiz['title'] . ' — EduTest']);
    }
}
