<?php

declare(strict_types=1);

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Services\AuthService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Validation;

final class AccountController extends BaseController
{
    public function index(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0');

        return view('teacher/account', [
            'title'         => lang('Workspace.myAccount') . ' — EduTest',
            'quizWorkspace' => false,
            'builderHeader' => false,
            'user'          => service('auth')->user(),
        ]);
    }

    public function updateProfile(): RedirectResponse
    {
        $user = service('auth')->user();
        $data = $this->normalizedProfileInput();
        $rules = config(Validation::class)->accountProfile;
        $rules['email']['rules'][] = 'is_unique[users.email,id,' . (int) $user['id'] . ']';
        $validation = service('validation');
        $validation->reset()->setRules($rules);

        if (! $validation->run($data)) {
            return redirect()->route('account')
                ->with('profileErrors', $validation->getErrors())
                ->with('profileOld', $data);
        }

        try {
            $updated = service('auth')->updateProfile(
                $data['display_name'],
                $data['email'],
                $data['phone'] === '' ? null : $data['phone'],
            );
        } catch (DatabaseException) {
            log_message('error', 'Account profile update failed because the account could not be stored.');

            return redirect()->route('account')
                ->with('profileErrors', ['email' => lang('Workspace.accountPage.emailTaken')])
                ->with('profileOld', $data);
        }

        if (! $updated) {
            return redirect()->route('account')
                ->with('profileError', lang('Workspace.accountPage.profileFailed'))
                ->with('profileOld', $data);
        }

        return redirect()->route('account')
            ->with('profileSuccess', lang('Workspace.accountPage.profileSaved'));
    }

    public function updatePassword(): RedirectResponse
    {
        $data = [
            'current_password'     => (string) $this->request->getPost('current_password'),
            'new_password'         => (string) $this->request->getPost('new_password'),
            'new_password_confirm' => (string) $this->request->getPost('new_password_confirm'),
        ];
        $validation = service('validation');
        $validation->reset()->setRules(config(Validation::class)->passwordChange);

        if (! $validation->run($data)) {
            return redirect()->route('account')
                ->with('passwordErrors', $validation->getErrors());
        }

        $result = service('auth')->changePassword($data['current_password'], $data['new_password']);
        if ($result === AuthService::PASSWORD_INCORRECT) {
            return redirect()->route('account')
                ->with('passwordErrors', ['current_password' => lang('Workspace.accountPage.currentPasswordIncorrect')]);
        }

        if ($result !== AuthService::PASSWORD_CHANGED) {
            return redirect()->route('account')
                ->with('passwordError', lang('Workspace.accountPage.passwordFailed'));
        }

        return redirect()->route('account')
            ->with('passwordSuccess', lang('Workspace.accountPage.passwordSaved'));
    }

    /** @return array{display_name: string, email: string, phone: string} */
    private function normalizedProfileInput(): array
    {
        $displayName = (string) $this->request->getPost('display_name');
        $trimmedName = preg_replace('/\A\s+|\s+\z/u', '', $displayName);
        $phone = (string) $this->request->getPost('phone');
        $phone = preg_replace('/[\s()\-]+/u', '', trim($phone)) ?? '';

        return [
            'display_name' => $trimmedName ?? trim($displayName),
            'email'        => strtolower(trim((string) $this->request->getPost('email'))),
            'phone'        => $phone,
        ];
    }
}
