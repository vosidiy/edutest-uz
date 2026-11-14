<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

/** Maps authenticated builder requests to QuizAuthoringService. */
final class QuizController extends ApiController
{
    // Create a teacher-owned draft quiz.
    public function create(): ResponseInterface
    {
        return $this->runApi(function (): array {
            $data = $this->jsonObject();
            return [
                ...service('quizAuthoring')->create(
                    $this->userId(),
                    (string) ($data['title'] ?? ''),
                ),
                '_status' => 201,
            ];
        });
    }

    // Load the teacher-owned editable quiz document for the builder.
    public function show(string $publicId): ResponseInterface
    {
        return $this->runApi(fn (): array => [
            'quiz' => service('quizAuthoring')->document($this->userId(), $publicId),
        ]);
    }

    // Validate and save the complete teacher quiz document.
    public function update(string $publicId): ResponseInterface
    {
        return $this->runApi(function () use ($publicId): array {
            $data = $this->jsonObject();
            return ['quiz' => service('quizAuthoring')->save($this->userId(), $publicId, $data)];
        });
    }

    // Move a quiz through its trash or restore action.
    public function transition(string $publicId, string $action): ResponseInterface
    {
        return $this->runApi(fn (): array => service('quizAuthoring')->transition(
            $this->userId(),
            $publicId,
            $action,
        ));
    }

    // Copy a quiz and its content into a new draft.
    public function duplicate(string $publicId): ResponseInterface
    {
        return $this->runApi(fn (): array => [
            ...service('quizAuthoring')->duplicate($this->userId(), $publicId),
            '_status' => 201,
        ]);
    }
}
