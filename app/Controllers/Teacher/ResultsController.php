<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Exceptions\ReportingException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

final class ResultsController extends BaseController
{
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url('dashboard'));
    }

    public function quiz(string $quizPublicId): string
    {
        $user = service('auth')->user();
        $ownerId = service('auth')->id();
        $this->privateResponse();

        try {
            $report = service('teacherResults')->quiz(
                (int) $ownerId,
                $quizPublicId,
                $this->attemptFilters(),
                (string) $user['timezone'],
            );
        } catch (ReportingException $exception) {
            $this->notFound($exception);
        }

        return view('teacher/results/quiz', [
            'title'     => $report['quiz']['title'] . ' — ' . lang('Results.nav'),
            'quizWorkspace' => true,
            'builderHeader' => false,
            'user'      => $user,
            'report'    => $report,
        ]);
    }

    public function attempt(string $attemptPublicId): string
    {
        $user = service('auth')->user();
        $ownerId = service('auth')->id();
        $this->privateResponse();

        try {
            $report = service('teacherResults')->attempt(
                (int) $ownerId,
                $attemptPublicId,
                (string) $user['timezone'],
            );
        } catch (ReportingException $exception) {
            $this->notFound($exception);
        }

        return view('teacher/results/attempt', [
            'title'     => lang('Results.attemptTitle') . ' — EduTest',
            'quizWorkspace' => true,
            'builderHeader' => false,
            'user'      => $user,
            'report'    => $report,
        ]);
    }

    /** @return array<string, mixed> */
    private function attemptFilters(): array
    {
        return [
            'q'         => $this->request->getGet('q'),
            'minScore'  => $this->request->getGet('minScore'),
            'dateRange' => $this->request->getGet('dateRange'),
            'page'      => $this->request->getGet('page'),
        ];
    }

    private function privateResponse(): void
    {
        $this->response
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    private function notFound(ReportingException $exception): never
    {
        if ($exception->status === 404) {
            throw PageNotFoundException::forPageNotFound();
        }
        throw $exception;
    }
}
