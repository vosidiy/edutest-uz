<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\AuthoringException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

abstract class ApiController extends BaseController
{
    /** @param mixed $data */
    protected function success($data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'data' => $data,
            'meta' => $this->meta(),
        ]);
    }

    protected function runApi(callable $operation): ResponseInterface
    {
        try {
            $result = $operation();
            $status = is_array($result) && isset($result['_status']) ? (int) $result['_status'] : 200;
            if (is_array($result)) {
                unset($result['_status']);
            }
            return $this->success($result, $status);
        } catch (AuthoringException $exception) {
            return $this->response->setStatusCode($exception->status)->setJSON([
                'error' => [
                    'code'    => $exception->errorCode,
                    'message' => $exception->getMessage(),
                    'fields'  => (object) $exception->fields,
                ],
                'meta' => $this->meta(),
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Authoring API failure ({type}).', ['type' => $exception::class]);
            return $this->response->setStatusCode(500)->setJSON([
                'error' => [
                    'code'    => 'server_error',
                    'message' => 'The request could not be completed. Please try again.',
                    'fields'  => (object) [],
                ],
                'meta' => $this->meta(),
            ]);
        }
    }

    /** @return array{csrfHeader: string, csrfToken: string, timestamp: string} */
    protected function meta(): array
    {
        return [
            'csrfHeader' => csrf_header(),
            'csrfToken'  => csrf_hash(),
            'timestamp'  => gmdate(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    protected function jsonObject(): array
    {
        $data = $this->request->getJSON(true);

        if (! is_array($data)) {
            throw new AuthoringException('invalid_json', 'A JSON object is required.', 400);
        }

        return $data;
    }

    protected function userId(): int
    {
        $id = service('auth')->id();

        if ($id === null) {
            throw new AuthoringException('authentication_required', 'Authentication is required.', 401);
        }

        return $id;
    }
}
