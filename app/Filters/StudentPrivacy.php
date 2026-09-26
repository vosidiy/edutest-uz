<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;

final class StudentPrivacy
{
    public static function matches(RequestInterface $request): bool
    {
        return preg_match('~(?:^|/)(?:api/v1/player|q|media)(?:/|$)~', $request->getUri()->getPath()) === 1;
    }
}
