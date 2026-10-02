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
        $this->assertStringContainsString('CREATE TABLE quiz_papers', $sql);
        $this->assertStringContainsString('paper_id BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringContainsString('current_paper_id BIGINT UNSIGNED NULL', $sql);
        $this->assertStringNotContainsString('first_started_at', $sql);
        $this->assertStringContainsString('share_token VARCHAR(64)', $sql);
        $this->assertStringNotContainsString('frozen_at', $sql);
        $this->assertStringContainsString('CREATE TABLE attempt_answers', $sql);
        $this->assertStringContainsString('presented_option_codes JSON NOT NULL', $sql);
        $this->assertStringNotContainsString('responses JSON', $sql);
        $this->assertStringNotContainsString('start_hash', $sql);
        $this->assertStringNotContainsString('current_pos', $sql);
        $this->assertStringNotContainsString('CREATE TABLE attempt_items', $sql);
        $this->assertStringContainsString("CHECK (status IN ('in_progress', 'completed', 'abandoned'))", $sql);
        $this->assertStringContainsString("ended_reason IN ('completed', 'quit', 'timer_expired', 'scheduled_close', 'stale_timeout')", $sql);
        $this->assertStringContainsString("CHECK (media_type IS NULL OR media_type = 'image')", $sql);
        $this->assertStringNotContainsString('points DECIMAL', $sql);
        $this->assertStringNotContainsString('chk_questions_time_limit', $sql);
        $this->assertStringNotContainsString('ix_item_due', $sql);
        $this->assertStringNotContainsString('question_timeout', $sql);
        $this->assertStringContainsString("type IN (\n        'tab_hidden',\n        'fullscreen_exit'", $sql);
        $this->assertStringNotContainsString("'window_blur'", $sql);
        $this->assertStringNotContainsString("'inactivity_start'", $sql);
        $this->assertStringNotContainsString('ALTER TABLE users', $sql);
    }
}
