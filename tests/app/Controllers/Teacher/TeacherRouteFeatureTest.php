<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Teacher;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class TeacherRouteFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        service('session')->destroy();
        $_SESSION = [];
    }

    public function testTeacherPagesRequireAuthentication(): void
    {
        $this->get('/dashboard')->assertRedirectTo(site_url('login'));
        $this->get('/quizzes')->assertRedirectTo(site_url('login'));
        $this->get('/quizzes/0123456789abcdef0123456789abcdef/edit')->assertRedirectTo(site_url('login'));
    }

    public function testAuthoringApiReturnsJsonAuthenticationError(): void
    {
        $response = $this->get('/api/v1/quizzes/0123456789abcdef0123456789abcdef');

        $response->assertStatus(401);
        $response->assertSee('authentication_required');
        $response->assertSee(csrf_header());
    }
}
