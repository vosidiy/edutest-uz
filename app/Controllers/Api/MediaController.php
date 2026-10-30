<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\AuthoringException;
use CodeIgniter\HTTP\ResponseInterface;

final class MediaController extends ApiController
{
    public function putCover(string $publicId): ResponseInterface
    {
        return $this->put($publicId, 'cover', 0);
    }

    public function deleteCover(string $publicId): ResponseInterface
    {
        return $this->delete($publicId, 'cover', 0);
    }

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
        ));
    }
}
