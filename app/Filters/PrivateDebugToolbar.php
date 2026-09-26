<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class PrivateDebugToolbar extends DebugToolbar
{
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return StudentPrivacy::matches($request) ? null : parent::after($request, $response, $arguments);
    }
}
