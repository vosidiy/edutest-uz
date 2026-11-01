<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Services\Player\AttemptSessionService;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;

final class PlayerController extends ApiController
{
    public function ticket(string $shareToken): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->admission->ticket($shareToken));
    }

    public function start(): ResponseInterface
    {
        return $this->run(function (): array {
            $data = service('player')->admission->start($this->input(), $this->request->getIPAddress(), $this->request->getHeaderLine('User-Agent'));
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

    public function show(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->load($id, $this->attemptCookie($id)));
    }

    public function progress(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->progress($id, $this->attemptCookie($id), $this->input()));
    }

    public function finish(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->finish($id, $this->attemptCookie($id), $this->input()));
    }

    public function results(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->results($id, $this->attemptCookie($id)));
    }

    public function assessmentMedia(string $id): ResponseInterface
    {
        return $this->run(function () use ($id): array {
            if ($this->input() !== []) throw new \App\Exceptions\PlayerException('invalid_progress');
            return service('player')->assessment->media($id, $this->attemptCookie($id));
        });
    }

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
