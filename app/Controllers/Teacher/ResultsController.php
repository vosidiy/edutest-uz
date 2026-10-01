<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Exceptions\ReportingException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\RedirectResponse;

final class ResultsController extends BaseController
{
    public function index(): RedirectResponse
    {
        return redirect()->to(service('teacherQueries')->legacyDashboardUrl($this->request->getGet(), 'results'));
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

    public function export(string $quizPublicId): ResponseInterface
    {
        $user = service('auth')->user();
        $ownerId = service('auth')->id();
        $reporting = service('teacherResults');

        try {
            $export = $reporting->export(
                (int) $ownerId,
                $quizPublicId,
                $this->attemptFilters(),
                (string) $user['timezone'],
            );
        } catch (ReportingException $exception) {
            $this->notFound($exception);
        }

        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('The CSV export could not be created.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Attempt ID', 'Name', 'Email', 'Phone', 'Status', 'Finish reason',
            'Score', 'Maximum score', 'Percentage', 'Started at', 'Finished at',
            'Duration seconds', 'Integrity events', 'Late synchronization',
        ], ',', '"', '');

        foreach ($export['rows'] as $row) {
            fputcsv($stream, array_map($reporting->protectCsvCell(...), array_values($row)), ',', '"', '');
        }

        rewind($stream);
        $body = stream_get_contents($stream);
        fclose($stream);
        if ($body === false) {
            throw new \RuntimeException('The CSV export could not be read.');
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string) $export['quiz']['title']) ?: 'quiz';
        $filename = trim(strtolower($slug), '-') . '-results.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($body);
    }

    /** @return array<string, mixed> */
    private function attemptFilters(): array
    {
        return [
            'q'         => $this->request->getGet('q'),
            'status'    => $this->request->getGet('status'),
            'dateFrom'  => $this->request->getGet('dateFrom'),
            'dateTo'    => $this->request->getGet('dateTo'),
            'minScore'  => $this->request->getGet('minScore'),
            'maxScore'  => $this->request->getGet('maxScore'),
            'integrity' => $this->request->getGet('integrity'),
            'sort'      => $this->request->getGet('sort'),
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
