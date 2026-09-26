<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\PageCache;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class PrivatePageCache extends PageCache
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return StudentPrivacy::matches($request) ? null : parent::before($request, $arguments);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return StudentPrivacy::matches($request) ? null : parent::after($request, $response, $arguments);
    }
}
