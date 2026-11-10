<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

final class PublicProfileController extends ApiController
{
    public function availability(): ResponseInterface
    {
        return $this->runApi(fn (): array => service('publicProfiles')->availability(
            $this->userId(),
            $this->request->getGet('username'),
        ));
    }

    public function update(): ResponseInterface
    {
        return $this->runApi(fn (): array => service('publicProfiles')->update(
            $this->userId(),
            $this->jsonObject(),
        ));
    }
}
