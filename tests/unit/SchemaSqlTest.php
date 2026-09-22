<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

final class SchemaSqlTest extends CIUnitTestCase
{
    public function testCanonicalSchemaContainsSingleTableAuthentication(): void
    {
        $sql = file_get_contents(ROOTPATH . 'docs/schema.sql');

        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE users', $sql);
        $this->assertStringContainsString('password_hash VARCHAR(255)', $sql);
        $this->assertStringContainsString('password_reset_hash BINARY(32)', $sql);
        $this->assertStringContainsString('user_id BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringNotContainsString('auth_identities', $sql);
        $this->assertStringNotContainsString('auth_groups', $sql);
        $this->assertStringNotContainsString('ALTER TABLE users', $sql);
    }
}
