<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Validation;

final class RegisterController extends BaseController
{
    private const RATE_CAPACITY = 5;
    private const RATE_SECONDS  = 3600;

    public function index(): string|RedirectResponse
    {
        if (service('auth')->check()) {
            return redirect()->to('/');
        }

        return view('auth/register');
    }

    public function create(): ResponseInterface
    {
        if (service('auth')->check()) {
            return redirect()->to('/');
        }

        $throttler = service('throttler');
        $rateKey   = 'auth-register:' . hash('sha256', $this->request->getIPAddress());

        if (! $throttler->check($rateKey, self::RATE_CAPACITY, self::RATE_SECONDS)) {
            return $this->response
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $throttler->getTokenTime())
                ->setBody(view('auth/register', [
                    'error' => lang('EduTest.tooManyRegistrations'),
                    'old'   => $this->safeOldInput(),
                ]));
        }

        $data       = $this->normalizedInput();
        $validation = service('validation');
        $validation->reset()->setRules(config(Validation::class)->registration);

        if (! $validation->run($data)) {
            return redirect()->route('register')
                ->with('errors', $validation->getErrors())
                ->with('old', $this->safeOldInput($data));
        }

        try {
            $userId = service('auth')->register(
                $data['email'],
                $data['password'],
                $data['display_name'],
                $data['phone'] === '' ? null : $data['phone'],
            );
        } catch (DatabaseException) {
            log_message('error', 'Registration failed because the account could not be stored.');
            $userId = null;
        }

        if ($userId === null) {
            return redirect()->route('register')
                ->with('error', lang('EduTest.registrationFailed'))
                ->with('old', $this->safeOldInput($data));
        }

        return redirect()->to('/');
    }

    /** @return array{display_name: string, email: string, phone: string, password: string, password_confirm: string} */
    private function normalizedInput(): array
    {
        $displayName = (string) $this->request->getPost('display_name');
        $trimmedName = preg_replace('/\A\s+|\s+\z/u', '', $displayName);
        $phone       = (string) $this->request->getPost('phone');
        $phone       = preg_replace('/[\s()\-]+/u', '', trim($phone)) ?? '';

        return [
            'display_name'     => $trimmedName ?? trim($displayName),
            'email'            => strtolower(trim((string) $this->request->getPost('email'))),
            'phone'            => $phone,
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
        ];
    }

    /** @param array<string, string>|null $data */
    private function safeOldInput(?array $data = null): array
    {
        $data ??= $this->normalizedInput();

        return [
            'display_name' => $data['display_name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'],
        ];
    }
}
