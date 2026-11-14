<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Services\Player\AttemptSessionService;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;

/** Exposes admission, progress, finish, and result operations for the student player. */
final class PlayerController extends ApiController
{
    // Issue a short-lived admission ticket for this public quiz.
    public function ticket(string $shareToken): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->admission->ticket($shareToken));
    }

    // Start the attempt and set its HttpOnly attempt cookie.
    public function start(): ResponseInterface
    {
        return $this->run(function (): array {
            $data = service('player')->admission->start($this->input(), $this->request->getIPAddress());
            $expires = new DateTimeImmutable($data['expiresAt'], new DateTimeZone('UTC'));
            $lifetime = max(1, $expires->getTimestamp() + AttemptSessionService::GRACE_SECONDS - time());
            $this->response->setCookie([
                'name'     => service('player')->sessions->cookieName($data['shareToken']),
                'value'    => $data['attemptId'],
                'expire'   => $lifetime,
                'path'     => '/',
                'secure'   => $this->request->isSecure(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            return service('player')->assessment->load($data['attemptId'], $data['attemptId']);
        });
    }

    // Load the cookie-owned attempt for the browser player.
    public function show(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->load($id, $this->attemptCookie($id)));
    }

    // Save a validated versioned answer checkpoint.
    public function progress(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->progress($id, $this->attemptCookie($id), $this->input()));
    }

    // Submit the complete attempt for authoritative grading.
    public function finish(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->finish($id, $this->attemptCookie($id), $this->input()));
    }

    // Return the cookie-owned attempt's result.
    public function results(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->results($id, $this->attemptCookie($id)));
    }

    // Renew signed media URLs for the active attempt.
    public function assessmentMedia(string $id): ResponseInterface
    {
        return $this->run(function () use ($id): array {
            if ($this->input() !== []) throw new \App\Exceptions\PlayerException('invalid_progress');
            return service('player')->assessment->media($id, $this->attemptCookie($id));
        });
    }

    // Read the cookie name associated with this attempt ID.
    private function attemptCookie(string $publicId): ?string
    {
        $name = service('player')->sessions->cookieNameForAttempt($publicId);
        if ($name === null) {
            return null;
        }
        $value = $this->request->getCookie($name);

        return is_string($value) ? $value : null;
    }

}
