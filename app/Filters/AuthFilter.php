<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! service('auth')->check()) {
            if (preg_match('~(?:^|/)api/~', $request->getUri()->getPath()) === 1) {
                return service('response')->setStatusCode(401)->setJSON([
                    'error' => [
                        'code'    => 'authentication_required',
                        'message' => 'Authentication is required.',
                        'fields'  => (object) [],
                    ],
                    'meta' => [
                        'csrfHeader' => csrf_header(),
                        'csrfToken'  => csrf_hash(),
                        'timestamp'  => gmdate(DATE_ATOM),
                    ],
                ]);
            }

            return redirect()->route('login');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
