<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\AuthoringException;
use CodeIgniter\HTTP\ResponseInterface;

/** Routes teacher-owned quiz media uploads and removals to MediaService. */
final class MediaController extends ApiController
{
    // Upload a quiz cover through the shared media handler.
    public function putCover(string $publicId): ResponseInterface
    {
        return $this->put($publicId, 'cover', 0);
    }

    // Remove a quiz cover through the shared media handler.
    public function deleteCover(string $publicId): ResponseInterface
    {
        return $this->delete($publicId, 'cover', 0);
    }

    // Upload question media through the shared media handler.
    public function putQuestion(string $publicId, string $questionId): ResponseInterface
    {
        return $this->put($publicId, 'question', (int) $questionId);
    }

    // Upload an answer image through the shared media handler.
    public function putOption(string $publicId, string $optionId): ResponseInterface
    {
        return $this->put($publicId, 'option', (int) $optionId);
    }

    // Remove question media through the shared media handler.
    public function deleteQuestion(string $publicId, string $questionId): ResponseInterface
    {
        return $this->delete($publicId, 'question', (int) $questionId);
    }

    // Remove an answer image through the shared media handler.
    public function deleteOption(string $publicId, string $optionId): ResponseInterface
    {
        return $this->delete($publicId, 'option', (int) $optionId);
    }

    // Validate an uploaded file and delegate storage to MediaService.
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

    // Delegate media removal to MediaService.
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
