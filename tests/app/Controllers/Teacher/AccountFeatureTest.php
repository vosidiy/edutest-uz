<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Teacher;

use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Filters;
use Config\Services;

final class AccountFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $accountDb;
    private Forge $forge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountDb = Database::connect('tests');
        $this->forge = Database::forge('tests');
        if ($this->accountDb->tableExists('users')) {
            $this->forge->dropTable('users', true);
        }
        $this->createUsersTable();
        service('session')->destroy();
        $_SESSION = [];
        Services::resetSingle('auth');
        Services::injectMock('auth', new AuthService(new UserModel($this->accountDb), service('session')));
    }

    protected function tearDown(): void
    {
        Services::resetSingle('auth');
        service('session')->destroy();
        $_SESSION = [];
        if ($this->accountDb->tableExists('users')) {
            $this->forge->dropTable('users', true);
        }

        parent::tearDown();
    }

    public function testAccountPageRequiresAuthenticationAndRendersBothForms(): void
    {
        $this->get('/account')->assertRedirectTo(site_url('login'));

        $userId = $this->insertUser();
        $response = $this->withSession([AuthService::SESSION_KEY => $userId])->get('/account');
        $response->assertOK();
        $response->assertSee('My account');
        $response->assertSee('id="profileForm"');
        $response->assertSee('id="passwordForm"');
        $response->assertSee('teacher@example.test');
        $response->assertSee(base_url('js/auth.js') . '?v=');
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
    }

    public function testProfileUpdateNormalizesSafeFieldsAndCannotTargetAnotherAccount(): void
    {
        $userId = $this->insertUser();
        $otherId = $this->insertUser('other@example.test');

        $response = $this->withoutGlobalFilters(fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/profile', [
            'id'           => $otherId,
            'display_name' => "  Ada Lovelace\u{2003}",
            'email'        => ' ADA@EXAMPLE.TEST ',
            'phone'        => '+998 (90) 123-45-67',
        ]));

        $response->assertRedirectTo(site_url('account'));
        $response->assertSessionHas('profileSuccess');
        $updated = $this->accountDb->table('users')->where('id', $userId)->get()->getRowArray();
        $other = $this->accountDb->table('users')->where('id', $otherId)->get()->getRowArray();
        $this->assertSame('Ada Lovelace', $updated['display_name']);
        $this->assertSame('ada@example.test', $updated['email']);
        $this->assertSame('+998901234567', $updated['phone']);
        $this->assertSame('Test Teacher', $other['display_name']);
    }

    public function testProfileAllowsCurrentEmailAndRemovingPhone(): void
    {
        $userId = $this->insertUser(phone: '+998901234567');
        $response = $this->withoutGlobalFilters(fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/profile', [
            'display_name' => 'Test Teacher',
            'email'        => 'teacher@example.test',
            'phone'        => '',
        ]));

        $response->assertRedirectTo(site_url('account'));
        $response->assertSessionHas('profileSuccess');
        $this->assertNull($this->accountDb->table('users')->where('id', $userId)->get()->getRow('phone'));
    }

    public function testProfileRejectsDuplicateAndInvalidInputWithoutChangingAccount(): void
    {
        $userId = $this->insertUser();
        $this->insertUser('used@example.test');

        $duplicate = $this->withoutGlobalFilters(fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/profile', [
            'display_name' => 'Changed Name',
            'email'        => 'used@example.test',
            'phone'        => '',
        ]));
        $duplicate->assertSessionHas('profileErrors');

        $invalid = $this->withoutGlobalFilters(fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/profile', [
            'display_name' => '',
            'email'        => 'not-an-email',
            'phone'        => 'letters',
        ]));
        $invalid->assertSessionHas('profileErrors');

        $user = $this->accountDb->table('users')->where('id', $userId)->get()->getRowArray();
        $this->assertSame('Test Teacher', $user['display_name']);
        $this->assertSame('teacher@example.test', $user['email']);
    }

    public function testPasswordChangeRejectsIncorrectAndInvalidCredentials(): void
    {
        $userId = $this->insertUser();
        $originalHash = $this->accountDb->table('users')->where('id', $userId)->get()->getRow('password_hash');

        $incorrect = $this->withoutGlobalFilters(fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/password', [
            'current_password'     => 'incorrect',
            'new_password'         => 'new-secret',
            'new_password_confirm' => 'new-secret',
        ]));
        $incorrect->assertSessionHas('passwordErrors');

        $invalid = $this->withoutGlobalFilters(fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/password', [
            'current_password'     => 'secret1',
            'new_password'         => 'short',
            'new_password_confirm' => 'different',
        ]));
        $invalid->assertSessionHas('passwordErrors');
        $invalid->assertSessionMissing('current_password');
        $invalid->assertSessionMissing('new_password');
        $invalid->assertSessionMissing('new_password_confirm');
        $this->assertSame($originalHash, $this->accountDb->table('users')->where('id', $userId)->get()->getRow('password_hash'));
    }

    public function testPasswordChangeRehashesAndKeepsCurrentSession(): void
    {
        $userId = $this->insertUser();
        $response = $this->withoutGlobalFilters(fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/password', [
            'current_password'     => 'secret1',
            'new_password'         => 'new-secret',
            'new_password_confirm' => 'new-secret',
        ]));

        $response->assertRedirectTo(site_url('account'));
        $response->assertSessionHas('passwordSuccess');
        $response->assertSessionHas(AuthService::SESSION_KEY, $userId);
        $hash = $this->accountDb->table('users')->where('id', $userId)->get()->getRow('password_hash');
        $this->assertFalse(password_verify('secret1', $hash));
        $this->assertTrue(password_verify('new-secret', $hash));
        $this->assertTrue(service('session')->didRegenerate);
    }

    public function testAccountMutationRequiresCsrfToken(): void
    {
        $this->expectException(SecurityException::class);
        $userId = $this->insertUser();

        $this->withSession([AuthService::SESSION_KEY => $userId])->post('/account/profile', [
            'display_name' => 'Test Teacher',
            'email'        => 'teacher@example.test',
            'phone'        => '',
        ]);
    }

    private function createUsersTable(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 254],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'display_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'bio' => ['type' => 'VARCHAR', 'constraint' => 1000, 'default' => ''],
            'timezone' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'Asia/Tashkent'],
            'public_page' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'password_reset_hash' => ['type' => 'BLOB', 'null' => true],
            'password_reset_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users', true);
    }

    private function insertUser(string $email = 'teacher@example.test', string $password = 'secret1', ?string $phone = null): int
    {
        $now = date('Y-m-d H:i:s');
        $this->accountDb->table('users')->insert([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
            'display_name' => 'Test Teacher',
            'phone' => $phone,
            'bio' => '',
            'timezone' => 'Asia/Tashkent',
            'public_page' => 0,
            'active' => 1,
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->accountDb->insertID();
    }

    private function withoutGlobalFilters(callable $request): mixed
    {
        $filters = config(Filters::class);
        $before = $filters->globals['before'];
        $after = $filters->globals['after'];
        $filters->globals['before'] = [];
        $filters->globals['after'] = [];

        try {
            return $request();
        } finally {
            $filters->globals['before'] = $before;
            $filters->globals['after'] = $after;
        }
    }
}
