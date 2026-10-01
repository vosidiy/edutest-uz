<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use CodeIgniter\HTTP\ResponseInterface;

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
            if ($data['mode'] === 'assessment') $data += service('player')->assessment->load($data['attemptId'], $data['credential']);
            return $data;
        });
    }

    public function show(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->load($id, $this->bearer()));
    }

    public function answer(string $id, string $questionId): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->answer($id, $questionId, $this->bearer(), $this->input()));
    }

    public function finish(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->finish($id, $this->bearer(), $this->input()));
    }

    public function activity(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->activity($id, $this->bearer(), $this->input()));
    }

    public function results(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->results($id, $this->bearer()));
    }

    public function events(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->events($id, $this->bearer(), $this->input()));
    }

    public function assessmentMedia(string $id): ResponseInterface
    {
        return $this->run(function () use ($id): array {
            if ($this->input() !== []) throw new \App\Exceptions\PlayerException('invalid_progress');
            return service('player')->assessment->media($id, $this->bearer());
        });
    }

    public function practiceMedia(): ResponseInterface
    {
        return $this->run(function (): array {
            if ($this->input() !== []) throw new \App\Exceptions\PlayerException('practice_anonymous');
            return service('player')->practice->media($this->bearer());
        });
    }
}
