<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Exceptions\AuthoringException;
use App\Exceptions\PlayerException;
use App\Services\Player\PlayerStore;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

abstract class ApiController extends BaseController
{
    protected function run(callable $operation): ResponseInterface
    {
        $status = 200;
        try { $body = ['data' => $operation()]; }
        catch (PlayerException|AuthoringException $error) {
            $status = $error->status;
            $body = ['error' => ['code' => $error->errorCode, 'message' => $error->getMessage(), 'fields' => (object) $error->fields]];
        } catch (Throwable $error) {
            // Never record a request, credential, answer, query, or practice body.
            log_message('error', 'Student API failure ({type}).', ['type' => $error::class]);
            $status = 500;
            $body = ['error' => ['code' => 'server_error', 'message' => lang('Player.errors.server_error'), 'fields' => (object) []]];
        }
        $body['meta'] = ['timestamp' => PlayerStore::iso(PlayerStore::now())];
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store, private')->setJSON($body);
    }

    protected function input(): array
    {
        $body = (string) $this->request->getBody();
        try { $value = json_decode($body, false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new PlayerException('invalid_json', 400); }
        if (! $value instanceof \stdClass) throw new PlayerException('invalid_json', 400);
        return json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    }

    protected function bearer(): string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/D', $header, $match)) throw new PlayerException('invalid_credential', 401);
        return $match[1];
    }
}
