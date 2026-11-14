<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Services\QuizShareCode;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/** Serves the public introduction and cookie-gated player pages; the student API owns mutations. */
final class QuizController extends BaseController
{
    // Show the public introduction unless an active cookie-owned attempt should resume.
    public function show(string $shareToken): string|ResponseInterface
    {
        if (! QuizShareCode::isValid($shareToken)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $attempt = $this->currentAttempt($shareToken);
        if ($attempt !== null && $this->isActive($attempt)) {
            return $this->redirectTo($shareToken, 'start');
        }
        $quiz = service('quizAuthoring')->publicSummary($shareToken);

        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->privateResponse();
        return view('student/quiz_form', ['quiz' => $quiz, 'title' => $quiz['title'] . ' — EduTest', 'shareToken' => $shareToken]);
    }

    // Show the in-progress player shell after checking the attempt cookie.
    public function start(string $shareToken): string|ResponseInterface
    {
        return $this->shell($shareToken, 'start');
    }

    // Show the completed result shell after checking the attempt cookie.
    public function results(string $shareToken): string|ResponseInterface
    {
        return $this->shell($shareToken, 'results');
    }

    // Serve the player shell for a cookie-owned attempt in the requested phase.
    private function shell(string $shareToken, string $page): string|ResponseInterface
    {
        if (! QuizShareCode::isValid($shareToken)) throw PageNotFoundException::forPageNotFound();
        $attempt = $this->currentAttempt($shareToken);
        if ($attempt === null) {
            return $this->redirectTo($shareToken);
        }
        $active = $this->isActive($attempt);
        if ($page === 'start' && ! $active) {
            return $this->redirectTo($shareToken, 'results');
        }
        if ($page === 'results' && $active) {
            return $this->redirectTo($shareToken, 'start');
        }

        // Existing cookie-authorized attempts remain available after a quiz returns to Draft or is soft-deleted.
        $this->privateResponse();
        return view('student/quiz_player', [
            'title' => lang('Player.ui.quizPlayer') . ' — EduTest',
            'shareToken' => $shareToken,
            'page' => $page,
            'attemptId' => (string) $attempt['public_id'],
        ]);
    }

    // Resolve the current attempt from this quiz's cookie.
    private function currentAttempt(string $shareToken): ?array
    {
        $name = service('player')->sessions->cookieName($shareToken);

        return service('player')->sessions->current($shareToken, $this->request->getCookie($name));
    }

    // Check whether the attempt is in progress and before its deadline.
    private function isActive(array $attempt): bool
    {
        return $attempt['status'] === 'in_progress'
            && \App\Services\Player\PlayerStore::now() < (string) $attempt['expires_at'];
    }

    // Redirect to the canonical page for this quiz phase.
    private function redirectTo(string $shareToken, ?string $page = null): ResponseInterface
    {
        $this->privateResponse();
        $url = site_url('q/' . $shareToken . ($page === null ? '' : '/' . $page));

        return redirect()->to($url);
    }

    // Prevent private quiz or result pages from being cached.
    private function privateResponse(): void
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')->setHeader('Referrer-Policy', 'no-referrer')->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
