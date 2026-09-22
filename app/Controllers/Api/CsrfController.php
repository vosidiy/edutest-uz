<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

final class CsrfController extends ApiController
{
    public function show(): ResponseInterface
    {
        return $this->success(['ready' => true]);
    }
}
