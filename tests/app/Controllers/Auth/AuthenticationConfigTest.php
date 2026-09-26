<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Auth;

use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\BaseController;
use App\Filters\AuthFilter;
use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Filters;
use Config\Services;
use Config\Validation;

final class AuthenticationConfigTest extends CIUnitTestCase
{
    public function testAuthenticationIsProjectOwned(): void
    {
        $this->assertTrue(is_subclass_of(LoginController::class, BaseController::class));
        $this->assertTrue(is_subclass_of(RegisterController::class, BaseController::class));
        $this->assertInstanceOf(AuthService::class, Services::auth(false));
    }

    public function testRegistrationRulesMatchThePublicForm(): void
    {
        $rules = (new Validation())->registration;

        $this->assertSame(
            ['display_name', 'email', 'phone', 'password', 'password_confirm'],
            array_keys($rules),
        );
        $this->assertContains('max_length[120]', $rules['display_name']['rules']);
        $this->assertContains('is_unique[users.email]', $rules['email']['rules']);
        $this->assertContains('regex_match[/^\+?[0-9]{7,15}$/]', $rules['phone']['rules']);
        $this->assertContains('min_length[6]', $rules['password']['rules']);
        $this->assertContains('max_byte[72]', $rules['password']['rules']);
        $this->assertContains('matches[password]', $rules['password_confirm']['rules']);
    }

    public function testCsrfAndAuthenticationFilterAreConfigured(): void
    {
        $filters = new Filters();

        $this->assertArrayHasKey('csrf', $filters->globals['before']);
        $this->assertSame(['q/*', 'api/v1/player/*', 'media/*'], $filters->globals['before']['csrf']['except']);
        $this->assertNotContains('api/v1/quizzes/*', $filters->globals['before']['csrf']['except']);
        $this->assertSame(AuthFilter::class, $filters->aliases['auth']);
        $this->assertSame([], $filters->filters);
    }
}
