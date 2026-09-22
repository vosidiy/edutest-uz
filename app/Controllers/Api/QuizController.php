<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

final class QuizController extends ApiController
{
    public function create(): ResponseInterface
    {
        return $this->runApi(function (): array {
            $data = $this->jsonObject();
            return [
                ...service('quizAuthoring')->create(
                    $this->userId(),
                    (string) ($data['title'] ?? ''),
                    (string) ($data['mode'] ?? ''),
                ),
                '_status' => 201,
            ];
        });
    }

    public function show(string $publicId): ResponseInterface
    {
        return $this->runApi(fn (): array => [
            'quiz' => service('quizAuthoring')->document($this->userId(), $publicId),
        ]);
    }

    public function update(string $publicId): ResponseInterface
    {
        return $this->runApi(function () use ($publicId): array {
            $data = $this->jsonObject();
            $overwrite = $this->request->getGet('overwrite') === '1';
            return ['quiz' => service('quizAuthoring')->save($this->userId(), $publicId, $data, $overwrite)];
        });
    }

    public function transition(string $publicId, string $action): ResponseInterface
    {
        return $this->runApi(fn (): array => service('quizAuthoring')->transition(
            $this->userId(),
            $publicId,
            $action,
        ));
    }

    public function duplicate(string $publicId): ResponseInterface
    {
        return $this->runApi(fn (): array => [
            ...service('quizAuthoring')->duplicate($this->userId(), $publicId),
            '_status' => 201,
        ]);
    }
}
