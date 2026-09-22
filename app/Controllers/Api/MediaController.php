<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\AuthoringException;
use CodeIgniter\HTTP\ResponseInterface;

final class MediaController extends ApiController
{
    public function putQuestion(string $publicId, string $questionId): ResponseInterface
    {
        return $this->put($publicId, 'question', (int) $questionId);
    }

    public function putOption(string $publicId, string $optionId): ResponseInterface
    {
        return $this->put($publicId, 'option', (int) $optionId);
    }

    public function deleteQuestion(string $publicId, string $questionId): ResponseInterface
    {
        return $this->delete($publicId, 'question', (int) $questionId);
    }

    public function deleteOption(string $publicId, string $optionId): ResponseInterface
    {
        return $this->delete($publicId, 'option', (int) $optionId);
    }

    private function put(string $publicId, string $target, int $targetId): ResponseInterface
    {
        return $this->runApi(function () use ($publicId, $target, $targetId): array {
            $contentType = strtolower($this->request->getHeaderLine('Content-Type'));
            if (str_contains($contentType, 'application/json')) {
                $data = $this->jsonObject();
                return service('media')->attachVideo(
                    $this->userId(),
                    $publicId,
                    $target,
                    $targetId,
                    (string) ($data['videoUrl'] ?? ''),
                    $this->version($data['version'] ?? null),
                );
            }

            $file = $this->request->getFile('media');
            if ($file === null) {
                throw new AuthoringException('invalid_upload', 'Choose a media file to upload.', 422);
            }
            return service('media')->attachFile(
                $this->userId(),
                $publicId,
                $target,
                $targetId,
                $file,
                $this->version($this->request->getPost('version')),
            );
        });
    }

    private function delete(string $publicId, string $target, int $targetId): ResponseInterface
    {
        return $this->runApi(fn (): array => service('media')->remove(
            $this->userId(),
            $publicId,
            $target,
            $targetId,
            $this->version($this->request->getGet('version')),
        ));
    }

    private function version(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new AuthoringException('version_required', 'A current quiz version is required.', 422);
        }

        return (int) $value;
    }
}
