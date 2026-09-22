<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Validation;

final class LoginController extends BaseController
{
    private const RATE_CAPACITY = 5;
    private const RATE_SECONDS  = 300;

    public function index(): string|RedirectResponse
    {
        if (service('auth')->check()) {
            return redirect()->to('/');
        }

        return view('auth/login');
    }

    public function authenticate(): ResponseInterface
    {
        if (service('auth')->check()) {
            return redirect()->to('/');
        }

        $email     = strtolower(trim((string) $this->request->getPost('email')));
        $password  = (string) $this->request->getPost('password');
        $rateKey   = $this->rateKey($email);
        $throttler = service('throttler');

        if (! $throttler->check($rateKey, self::RATE_CAPACITY, self::RATE_SECONDS)) {
            return $this->response
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $throttler->getTokenTime())
                ->setBody(view('auth/login', [
                    'error' => lang('EduTest.tooManyLogins'),
                    'old'   => ['email' => $email],
                ]));
        }

        $data       = ['email' => $email, 'password' => $password];
        $validation = service('validation');
        $validation->reset()->setRules(config(Validation::class)->login);

        if (! $validation->run($data)) {
            return redirect()->route('login')
                ->with('errors', $validation->getErrors())
                ->with('old', ['email' => $email]);
        }

        if (! service('auth')->attempt($email, $password)) {
            return redirect()->route('login')
                ->with('error', lang('EduTest.invalidCredentials'))
                ->with('old', ['email' => $email]);
        }

        $throttler->remove($rateKey);

        return redirect()->to('/');
    }

    public function logout(): RedirectResponse
    {
        service('auth')->logout();

        return redirect()->route('login');
    }

    private function rateKey(string $email): string
    {
        return 'auth-login:' . hash(
            'sha256',
            $this->request->getIPAddress() . "\0" . $email,
        );
    }
}
