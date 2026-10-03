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
        $result->assertSee("Test yarating. Onlayn imtihon o'tkazing");
        $result->assertSee('Kimlar uchun?');
        $result->assertSee("Ko'plab imkoniyatlar");
        $result->assertSee('css/landing.css');
        $result->assertSee('images/landing/intro.png');
        $result->assertSee('images/landing/placeholder.jpg');
        $result->assertSee('/register');
        $result->assertSee('/login');
        $result->assertDontSee('quiz-modes-title');
        $result->assertDontSee('Ikki rejim');
        $result->assertDontSee('assets/');
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
        $this->assertStringContainsString('Ish maydoniga otish', $body);
        $this->assertStringNotContainsString('/register', $body);
        $this->assertStringNotContainsString('/login', $body);
        $this->assertStringNotContainsString('/assets/', $body);
    }

    public function testLandingAssetsAndRenamedViewExist(): void
    {
        $this->assertFileExists(APPPATH . 'Views/landing_page.php');
        $this->assertFileDoesNotExist(APPPATH . 'Views/welcome_message.php');
        $this->assertFileExists(FCPATH . 'css/landing.css');
        $this->assertFileExists(FCPATH . 'js/landing.js');
        $this->assertFileExists(FCPATH . 'js/vue.global.prod.js');
        $this->assertFileExists(FCPATH . 'js/vue.LICENSE.txt');
        $this->assertFileExists(FCPATH . 'images/landing/intro.png');
        $this->assertFileExists(FCPATH . 'images/landing/placeholder.jpg');
        $this->assertFileExists(FCPATH . 'fonts/inter/Inter-Regular.woff2');
        $this->assertFileExists(FCPATH . 'fonts/inter/Inter-Medium.woff2');
        $this->assertFileExists(FCPATH . 'fonts/inter/Inter-SemiBold.woff2');
        $this->assertDirectoryDoesNotExist(FCPATH . 'assets');
        $this->assertDirectoryDoesNotExist(FCPATH . 'vendor');
    }
}
