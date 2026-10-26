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
            return $data + service('player')->assessment->load($data['attemptId'], $data['credential']);
        });
    }

    public function show(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->load($id, $this->bearer()));
    }

    public function progress(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->progress($id, $this->bearer(), $this->input()));
    }

    public function finish(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->finish($id, $this->bearer(), $this->input()));
    }

    public function results(string $id): ResponseInterface
    {
        return $this->run(fn (): array => service('player')->assessment->results($id, $this->bearer()));
    }

    public function assessmentMedia(string $id): ResponseInterface
    {
        return $this->run(function () use ($id): array {
            if ($this->input() !== []) throw new \App\Exceptions\PlayerException('invalid_progress');
            return service('player')->assessment->media($id, $this->bearer());
        });
    }

}
