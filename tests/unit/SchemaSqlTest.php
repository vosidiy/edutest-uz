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
        $this->assertStringContainsString('share_token CHAR(9)', $sql);
        $this->assertStringContainsString('time_limit_sec SMALLINT UNSIGNED NOT NULL DEFAULT 7200', $sql);
        $this->assertStringContainsString('draw_count SMALLINT UNSIGNED NULL', $sql);
        $this->assertStringContainsString('options_layout VARCHAR(8)', $sql);
        $this->assertStringContainsString('snapshot JSON NOT NULL', $sql);
        $this->assertStringContainsString('answers JSON NOT NULL', $sql);
        $this->assertStringContainsString('answer_version INT UNSIGNED NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString('CONSTRAINT uq_attempts_quiz_start_key UNIQUE (quiz_id, start_key)', $sql);
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

    public function testDevelopmentResetPreservesUsersAndRecreatesOnlyCurrentQuizTables(): void
    {
        $sql = file_get_contents(ROOTPATH . 'docs/attempt-snapshot-reset.sql');

        $this->assertIsString($sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 1', $sql);
        $this->assertStringNotContainsString('DROP TABLE IF EXISTS users', $sql);
        $this->assertStringNotContainsString('CREATE TABLE users', $sql);
        foreach (['quizzes', 'questions', 'question_options', 'attempts'] as $table) {
            $this->assertStringContainsString('DROP TABLE IF EXISTS ' . $table, $sql);
            $this->assertStringContainsString('CREATE TABLE ' . $table, $sql);
        }
        foreach (['quiz_papers', 'attempt_answers', 'practice_keys', 'cheat_events'] as $table) {
            $this->assertStringContainsString('DROP TABLE IF EXISTS ' . $table, $sql);
            $this->assertStringNotContainsString('CREATE TABLE ' . $table, $sql);
        }
        $this->assertStringContainsString('snapshot JSON NOT NULL', $sql);
        $this->assertStringContainsString('answers JSON NOT NULL', $sql);
    }
}
