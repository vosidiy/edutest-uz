<?php

declare(strict_types=1);

namespace Tests\App\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Session;

final class SessionConfigurationTest extends CIUnitTestCase
{
    public function testTeacherSessionUsesOneYearPersistentLifetime(): void
    {
        $config = config(Session::class);

        self::assertSame(31_536_000, $config->expiration);
        self::assertGreaterThan(0, $config->expiration);
        self::assertSame(300, $config->timeToUpdate);
    }
}
