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
        $this->assertStringContainsString('username VARCHAR(30)', $sql);
        $this->assertStringContainsString('CONSTRAINT uq_users_username UNIQUE (username)', $sql);
        $this->assertStringContainsString('CONSTRAINT chk_users_public_page_username', $sql);
        $this->assertStringNotContainsString('REGEXP BINARY', $sql);
        $this->assertStringContainsString('password_reset_hash BINARY(32)', $sql);
        $this->assertStringContainsString('user_id BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringNotContainsString('auth_identities', $sql);
        $this->assertStringNotContainsString('auth_groups', $sql);
        $this->assertStringContainsString('share_token CHAR(9)', $sql);
        $this->assertStringContainsString('time_limit_sec SMALLINT UNSIGNED NOT NULL DEFAULT 7200', $sql);
        $this->assertStringContainsString('draw_count SMALLINT UNSIGNED NULL', $sql);
        $this->assertStringContainsString('show_answers TINYINT(1) NOT NULL DEFAULT 0', $sql);
        $this->assertStringNotContainsString('show_score', $sql);
        $this->assertStringNotContainsString('show_explain', $sql);
        $this->assertStringContainsString('options_layout VARCHAR(8)', $sql);
        $this->assertStringContainsString('snapshot JSON NOT NULL', $sql);
        $this->assertStringContainsString('answers JSON NOT NULL', $sql);
        $this->assertStringContainsString('answer_version INT UNSIGNED NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString('CONSTRAINT uq_attempts_public_id UNIQUE (public_id)', $sql);
        preg_match('/CREATE TABLE attempts \((.*?)\) ENGINE=InnoDB/s', $sql, $attemptTable);
        $this->assertArrayHasKey(1, $attemptTable);
        $this->assertStringContainsString('ip VARCHAR(45)', $attemptTable[1]);
        $this->assertStringNotContainsString('agent VARCHAR', $attemptTable[1]);
        $this->assertStringNotContainsString('token_hash', $sql);
        $this->assertStringNotContainsString('start_key', $sql);
        $this->assertStringContainsString('CONSTRAINT chk_attempts_score_percent CHECK', $sql);
        $this->assertStringContainsString("CHECK (status IN ('in_progress', 'completed', 'abandoned'))", $sql);
        $this->assertStringContainsString("ended_reason IN ('completed', 'exited', 'expired')", $sql);
        $this->assertStringContainsString("CHECK (media_type IS NULL OR media_type = 'image')", $sql);
        $this->assertStringContainsString("CHECK (media_type IS NULL OR media_type IN ('image', 'audio'))", $sql);
        $this->assertStringNotContainsString("media_type IN ('image', 'audio', 'video')", $sql);
        preg_match('/CREATE TABLE quizzes \((.*?)\) ENGINE=InnoDB/s', $sql, $quizTable);
        $this->assertArrayHasKey(1, $quizTable);
        $this->assertStringNotContainsString(' listed ', ' ' . $quizTable[1] . ' ');
        $this->assertStringNotContainsString("\n  version ", $quizTable[1]);
        $this->assertStringNotContainsString('ix_quiz_listed', $quizTable[1]);
        $this->assertStringNotContainsString('chk_quizzes_listed', $quizTable[1]);
        foreach (['quiz_papers', 'attempt_answers', 'practice_keys', 'cheat_events', 'mode ', 'current_paper_id', 'text_answers'] as $removed) {
            $this->assertStringNotContainsString($removed, $sql);
        }
        $this->assertStringNotContainsString('ALTER TABLE users', $sql);
    }

}
