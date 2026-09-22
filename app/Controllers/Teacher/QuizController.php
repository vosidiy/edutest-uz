<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Exceptions\AuthoringException;
use CodeIgniter\Exceptions\PageNotFoundException;

final class QuizController extends BaseController
{
    public function index(): string
    {
        return $this->library('active');
    }

    public function archived(): string
    {
        return $this->library('archived');
    }

    public function trash(): string
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
            'activeNav' => 'quizzes',
            'user'      => $user,
            'quiz'      => $quiz,
        ]);
    }

    private function library(string $view): string
    {
        $user = service('auth')->user();
        $filters = [
            'q'      => $this->request->getGet('q'),
            'status' => $this->request->getGet('status'),
            'mode'   => $this->request->getGet('mode'),
            'sort'   => $this->request->getGet('sort'),
            'page'   => $this->request->getGet('page'),
        ];

        return view('teacher/quizzes', [
            'title'     => 'My quizzes — EduTest',
            'activeNav' => 'quizzes',
            'user'      => $user,
            'library'   => service('teacherQueries')->library((int) $user['id'], $filters, $view),
        ]);
    }
}
