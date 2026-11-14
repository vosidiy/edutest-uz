<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Exceptions\AuthoringException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/** Serves the teacher quiz workspace while the API handles authoring writes. */
final class QuizController extends BaseController
{
    // Show the teacher's quiz library workspace.
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url('dashboard'));
    }

    // Load the owned quiz document into the builder view.
    public function edit(string $publicId): string
    {
        $user = service('auth')->user();
        try {
            $quiz = service('quizAuthoring')->document((int) $user['id'], $publicId);
        } catch (AuthoringException $exception) {
            if ($exception->status === 404) {
                throw PageNotFoundException::forPageNotFound();
            }
            throw $exception;
        }

        return view('teacher/builder', [
            'title'     => 'Quiz builder — EduTest',
            'quizWorkspace' => true,
            'builderHeader' => true,
            'user'      => $user,
            'quiz'      => $quiz,
        ]);
    }
}
