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
        $result->assertSee('One question at a time. Every answer counts.');
        $result->assertSee('Assessment');
        $result->assertSee('Practice');
        $result->assertSee('/assets/css/landing.css');
    }
}
