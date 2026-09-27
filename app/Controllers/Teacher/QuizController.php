<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Exceptions\AuthoringException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

final class QuizController extends BaseController
{
    public function index(): RedirectResponse
    {
        return $this->library('active');
    }

    public function archived(): RedirectResponse
    {
        return $this->library('archived');
    }

    public function trash(): RedirectResponse
    {
        return $this->library('trash');
    }

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

    private function library(string $view): RedirectResponse
    {
        return redirect()->to(service('teacherQueries')->legacyDashboardUrl($this->request->getGet(), $view));
    }
}
