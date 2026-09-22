<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Auth;

use App\Services\AuthService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Filters;
use Config\Services;

final class AuthenticationFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $db;
    private Forge $forge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db    = Database::connect('tests');
        $this->forge = Database::forge('tests');

        if ($this->db->tableExists('users')) {
            $this->forge->dropTable('users', true);
        }

        $this->createUsersTable();
        service('cache')->clean();
        service('session')->destroy();
        $_SESSION = [];
        Services::resetSingle('auth');
    }

    protected function tearDown(): void
    {
        Services::resetSingle('auth');
        service('session')->destroy();
        $_SESSION = [];

        if ($this->db->tableExists('users')) {
            $this->forge->dropTable('users', true);
        }

        parent::tearDown();
    }

    public function testRegistrationStoresOneAccountAndStartsSession(): void
    {
        $result = $this->withoutGlobalFilters(fn () => $this->post('/register', [
            'display_name'     => "  Ada Lovelace\u{2003}",
            'email'            => '  ADA@EXAMPLE.TEST ',
            'phone'            => '+998 (90) 123-45-67',
            'password'         => 'secret1',
            'password_confirm' => 'secret1',
        ]));

        $result->assertRedirectTo(rtrim(site_url('/'), '/'));
        $result->assertSessionHas(AuthService::SESSION_KEY);
        $result->assertSessionMissing('password');
        $result->assertSessionMissing('password_confirm');

        $user = $this->db->table('users')->get()->getRowArray();
        $this->assertNotNull($user);
        $this->assertSame('ada@example.test', $user['email']);
        $this->assertSame('Ada Lovelace', $user['display_name']);
        $this->assertSame('+998901234567', $user['phone']);
        $this->assertSame('', $user['bio']);
        $this->assertSame('Asia/Tashkent', $user['timezone']);
        $this->assertSame(0, (int) $user['public_page']);
        $this->assertSame(1, (int) $user['active']);
        $this->assertNotNull($user['last_login_at']);
        $this->assertNotSame('secret1', $user['password_hash']);
        $this->assertTrue(password_verify('secret1', $user['password_hash']));
        $this->assertTrue(service('session')->didRegenerate);
    }

    public function testRegistrationAllowsAnEmptyPhone(): void
    {
        $data          = $this->validRegistration();
        $data['phone'] = '';

        $this->withoutGlobalFilters(fn () => $this->post('/register', $data));

        $user = $this->db->table('users')->get()->getRowArray();
        $this->assertNull($user['phone']);
    }

    /** @dataProvider invalidRegistrationProvider */
    public function testRegistrationRejectsInvalidInput(array $changes): void
    {
        $data = [...$this->validRegistration(), ...$changes];

        $result = $this->withoutGlobalFilters(fn () => $this->post('/register', $data));

        $result->assertRedirect();
        $result->assertSessionHas('errors');
        $result->assertSessionMissing('password');
        $this->assertSame(0, $this->db->table('users')->countAllResults());
    }

    public static function invalidRegistrationProvider(): iterable
    {
        yield 'missing display name' => [['display_name' => '   ']];
        yield 'oversized display name' => [['display_name' => str_repeat('A', 121)]];
        yield 'invalid email' => [['email' => 'not-an-email']];
        yield 'invalid phone' => [['phone' => '+12 ABC 45']];
        yield 'short password' => [['password' => '12345', 'password_confirm' => '12345']];
        yield 'password over 72 bytes' => [['password' => str_repeat('é', 37), 'password_confirm' => str_repeat('é', 37)]];
        yield 'confirmation mismatch' => [['password_confirm' => 'different']];
    }

    public function testDuplicateEmailIsRejectedCaseInsensitively(): void
    {
        $this->insertUser(email: 'teacher@example.test');
        $data          = $this->validRegistration();
        $data['email'] = 'TEACHER@EXAMPLE.TEST';

        $result = $this->withoutGlobalFilters(fn () => $this->post('/register', $data));

        $result->assertRedirect();
        $result->assertSessionHas('errors');
        $this->assertSame(1, $this->db->table('users')->countAllResults());
    }

    public function testLoginNormalizesEmailUpdatesHashAndStartsSession(): void
    {
        $userId  = $this->insertUser();
        $oldHash = $this->db->table('users')->where('id', $userId)->get()->getRow('password_hash');

        $result = $this->withoutGlobalFilters(fn () => $this->post('/login', [
            'email'    => ' TEACHER@EXAMPLE.TEST ',
            'password' => 'secret1',
        ]));

        $result->assertRedirectTo(rtrim(site_url('/'), '/'));
        $result->assertSessionHas(AuthService::SESSION_KEY, $userId);

        $user = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        $this->assertNotNull($user['last_login_at']);
        $this->assertNotSame($oldHash, $user['password_hash']);
        $this->assertTrue(password_verify('secret1', $user['password_hash']));
        $this->assertFalse(password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT));
    }

    /** @dataProvider unavailableAccountProvider */
    public function testLoginUsesTheSameErrorForUnavailableAccounts(?array $account, string $loginPassword): void
    {
        if ($account !== null) {
            $this->insertUser(...$account);
        }

        $result = $this->withoutGlobalFilters(fn () => $this->post('/login', [
            'email'    => 'teacher@example.test',
            'password' => $loginPassword,
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('error', lang('EduTest.invalidCredentials'));
        $result->assertSessionMissing(AuthService::SESSION_KEY);
    }

    public static function unavailableAccountProvider(): iterable
    {
        yield 'missing' => [null, 'secret1'];
        yield 'incorrect password' => [[], 'incorrect'];
        yield 'inactive' => [['active' => 0], 'secret1'];
        yield 'soft deleted' => [['deleted' => true], 'secret1'];
    }

    public function testAuthenticatedUserIsRedirectedAwayFromLoginAndRegistration(): void
    {
        $userId  = $this->insertUser();
        $session = [AuthService::SESSION_KEY => $userId];

        $login = $this->withoutGlobalFilters(
            fn () => $this->withSession($session)->get('/login'),
        );
        $register = $this->withoutGlobalFilters(
            fn () => $this->withSession($session)->get('/register'),
        );

        $login->assertRedirectTo(rtrim(site_url('/'), '/'));
        $register->assertRedirectTo(rtrim(site_url('/'), '/'));
    }

    public function testPostLogoutClearsTheSession(): void
    {
        $userId = $this->insertUser();

        $result = $this->withoutGlobalFilters(
            fn () => $this->withSession([AuthService::SESSION_KEY => $userId])->post('/logout'),
        );

        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionMissing(AuthService::SESSION_KEY);
        $this->assertTrue(service('session')->didRegenerate);
    }

    public function testLogoutIsPostOnly(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->withoutGlobalFilters(fn () => $this->get('/logout'));
    }

    public function testRegistrationWithoutCsrfTokenIsRejected(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('/register', $this->validRegistration());
    }

    public function testLoginIsRateLimited(): void
    {
        $request = fn () => $this->withoutGlobalFilters(fn () => $this->post('/login', [
            'email'    => 'missing@example.test',
            'password' => 'incorrect',
        ]));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $request()->assertRedirect();
        }

        $limited = $request();
        $limited->assertStatus(429);
        $limited->assertHeader('Retry-After');
    }

    private function createUsersTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
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

    private function withoutGlobalFilters(callable $request): mixed
    {
        /** @var Filters $filters */
        $filters = config(Filters::class);
        $before  = $filters->globals['before'];
        $after   = $filters->globals['after'];

        $filters->globals['before'] = [];
        $filters->globals['after']  = [];

        try {
            return $request();
        } finally {
            $filters->globals['before'] = $before;
            $filters->globals['after']  = $after;
        }
    }

    /** @return array<string, string> */
    private function validRegistration(): array
    {
        return [
            'display_name'     => 'Grace Hopper',
            'email'            => 'grace@example.test',
            'phone'            => '',
            'password'         => 'secret1',
            'password_confirm' => 'secret1',
        ];
    }

    private function insertUser(
        string $email = 'teacher@example.test',
        string $password = 'secret1',
        int $active = 1,
        bool $deleted = false,
    ): int {
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
            'display_name'  => 'Test Teacher',
            'phone'         => null,
            'bio'           => '',
            'timezone'      => 'Asia/Tashkent',
            'public_page'   => 0,
            'active'        => $active,
            'last_login_at' => null,
            'created_at'    => $now,
            'updated_at'    => $now,
            'deleted_at'    => $deleted ? $now : null,
        ]);

        return (int) $this->db->insertID();
    }
}
