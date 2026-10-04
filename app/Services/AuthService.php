<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Session\SessionInterface;

final class AuthService
{
    public const SESSION_KEY = 'auth_user_id';
    public const PASSWORD_CHANGED = 'changed';
    public const PASSWORD_INCORRECT = 'incorrect';
    public const PASSWORD_FAILED = 'failed';

    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private readonly UserModel $users,
        private readonly SessionInterface $session,
    ) {
    }

    public function register(
        string $email,
        string $password,
        string $displayName,
        ?string $phone,
    ): ?int {
        $now  = Time::now('UTC')->format('Y-m-d H:i:s');
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $id = $this->users->insert([
            'email'         => $email,
            'password_hash' => $hash,
            'display_name'  => $displayName,
            'phone'         => $phone,
            'bio'           => '',
            'timezone'      => 'Asia/Tashkent',
            'public_page'   => false,
            'last_login_at' => $now,
        ], true);

        if ($id === false) {
            return null;
        }

        $userId = (int) $id;
        $this->startSession($userId);

        return $userId;
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findActiveByEmail($email);
        $hash = is_array($user) ? (string) $user['password_hash'] : self::DUMMY_PASSWORD_HASH;

        if (! password_verify($password, $hash) || $user === null) {
            return false;
        }

        $changes = [
            'last_login_at' => Time::now('UTC')->format('Y-m-d H:i:s'),
        ];

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $changes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $userId = (int) $user['id'];

        if (! $this->users->update($userId, $changes)) {
            return false;
        }

        $this->startSession($userId);

        return true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        $user = $this->users->findActiveById((int) $id);

        if ($user === null) {
            $this->session->remove(self::SESSION_KEY);
        }

        return $user;
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerate(true);
    }

    public function updateProfile(string $displayName, string $email, ?string $phone): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $changes = [];
        foreach (['display_name' => $displayName, 'email' => $email, 'phone' => $phone] as $field => $value) {
            if ($user[$field] !== $value) {
                $changes[$field] = $value;
            }
        }

        return $changes === [] || $this->users->update((int) $user['id'], $changes);
    }

    public function changePassword(string $currentPassword, string $newPassword): string
    {
        $user = $this->user();
        if ($user === null || ! password_verify($currentPassword, (string) $user['password_hash'])) {
            return self::PASSWORD_INCORRECT;
        }

        if (! $this->users->update((int) $user['id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ])) {
            return self::PASSWORD_FAILED;
        }

        $this->startSession((int) $user['id']);

        return self::PASSWORD_CHANGED;
    }

    private function startSession(int $userId): void
    {
        $this->session->regenerate(true);
        $this->session->set(self::SESSION_KEY, $userId);
    }
}
