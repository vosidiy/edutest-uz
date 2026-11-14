<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Exceptions\ReportingException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/** Serves teacher result pages built by TeacherResultsService. */
final class ResultsController extends BaseController
{
    // Show the teacher's results entry page.
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url('dashboard'));
    }

    // Load the owned quiz's paginated attempt list.
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

    // Load a single owned attempt and its question review.
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
    // Read search, date, and score query filters.
    private function attemptFilters(): array
    {
        return [
            'q'         => $this->request->getGet('q'),
            'minScore'  => $this->request->getGet('minScore'),
            'dateRange' => $this->request->getGet('dateRange'),
            'page'      => $this->request->getGet('page'),
        ];
    }

    // Prevent private quiz or result pages from being cached.
    private function privateResponse(): void
    {
        $this->response
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    // Translate a missing owned report into a public 404.
    private function notFound(ReportingException $exception): never
    {
        if ($exception->status === 404) {
            throw PageNotFoundException::forPageNotFound();
        }
        throw $exception;
    }
}
