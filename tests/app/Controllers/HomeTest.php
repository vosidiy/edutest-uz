<?php

namespace Tests\App\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class HomeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testLandingPageIsAvailable(): void
    {
        $result = $this->get('/');

        $result->assertStatus(200);
        $result->assertSee('Test yarating. Ulashing.');
        $result->assertSee('Asosiy imkoniyatlar');
        $result->assertSee('Baholash rejimi');
        $result->assertSee('Mashq rejimi');
        $result->assertSee('Maktablar');
        $result->assertSee('IELTS markazlari');
        $result->assertSee('Mustaqil o\u2018qituvchilar');
        $result->assertSee('Rekruting jamoalari');
        $result->assertSee('assets/css/landing.css');
        $result->assertSee('assets/images/landing/intro.png');
        $result->assertSee('assets/images/landing/placeholder.jpg');
        $result->assertSee('/register');
        $result->assertSee('/login');
        $this->assertStringNotContainsString('support-name', $result->getBody());
        $this->assertStringNotContainsString('Qo\u2018llab-quvvatlash', $result->getBody());
    }

    public function testAuthenticatedLandingUsesDashboardActions(): void
    {
        $body = html_entity_decode(
            view('landing_page', ['authenticated' => true]),
            ENT_QUOTES | ENT_HTML5,
        );

        $this->assertStringContainsString('/dashboard', $body);
        $this->assertStringContainsString('Ish maydoniga o\u2018tish', $body);
        $this->assertStringNotContainsString('/register', $body);
        $this->assertStringNotContainsString('/login', $body);
    }

    public function testLandingAssetsAndRenamedViewExist(): void
    {
        $this->assertFileExists(APPPATH . 'Views/landing_page.php');
        $this->assertFileDoesNotExist(APPPATH . 'Views/welcome_message.php');
        $this->assertFileExists(FCPATH . 'assets/css/landing.css');
        $this->assertFileExists(FCPATH . 'assets/js/landing.js');
        $this->assertFileExists(FCPATH . 'assets/images/landing/intro.png');
        $this->assertFileExists(FCPATH . 'assets/images/landing/placeholder.jpg');
        $this->assertFileExists(FCPATH . 'assets/fonts/inter/Inter-Regular.woff2');
        $this->assertFileExists(FCPATH . 'assets/fonts/inter/Inter-Medium.woff2');
        $this->assertFileExists(FCPATH . 'assets/fonts/inter/Inter-SemiBold.woff2');
    }
}
