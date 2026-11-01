<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Services\QuizShareCode;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

final class QuizController extends BaseController
{
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
        return view('public/quiz', ['quiz' => $quiz, 'title' => $quiz['title'] . ' — EduTest', 'shareToken' => $shareToken, 'page' => 'intro']);
    }

    public function start(string $shareToken): string|ResponseInterface
    {
        return $this->shell($shareToken, 'start');
    }

    public function results(string $shareToken): string|ResponseInterface
    {
        return $this->shell($shareToken, 'results');
    }

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
        return view('student/player', [
            'title' => lang('Player.ui.quizPlayer') . ' — EduTest',
            'shareToken' => $shareToken,
            'page' => $page,
            'attemptId' => (string) $attempt['public_id'],
        ]);
    }

    private function currentAttempt(string $shareToken): ?array
    {
        $name = service('player')->sessions->cookieName($shareToken);

        return service('player')->sessions->current($shareToken, $this->request->getCookie($name));
    }

    private function isActive(array $attempt): bool
    {
        return $attempt['status'] === 'in_progress'
            && \App\Services\Player\PlayerStore::now() < (string) $attempt['expires_at'];
    }

    private function redirectTo(string $shareToken, ?string $page = null): ResponseInterface
    {
        $this->privateResponse();
        $url = site_url('q/' . $shareToken . ($page === null ? '' : '/' . $page));

        return redirect()->to($url);
    }

    private function privateResponse(): void
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')->setHeader('Referrer-Policy', 'no-referrer')->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
