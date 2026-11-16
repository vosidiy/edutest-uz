-- 123test canonical application schema
-- Target: MySQL 8.4, InnoDB, utf8mb4
-- Last updated: 2026-11-16
--
-- Fresh-install use in phpMyAdmin:
--   1. Select the intended empty database.
--   2. Import this file once.
--
-- This is a complete fresh-install schema, not an upgrade script.
-- Columns and keys match docs/db-backup.sql (2026-11-16); that dump has no
-- CHECK constraints or table options. Keep explicit InnoDB/utf8mb4 defaults
-- here; confirm the source defaults separately before restoring the dump.

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+00:00';

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  username VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  phone VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
  bio VARCHAR(1000) NOT NULL DEFAULT '',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Tashkent',
  public_page TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  password_reset_hash BINARY(32) NULL,
  password_reset_expires_at DATETIME(6) NULL,
  last_login_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  deleted_at DATETIME(6) NULL,
  CONSTRAINT uq_users_email UNIQUE (email),
  CONSTRAINT uq_users_username UNIQUE (username),
  INDEX ix_users_public (public_page, active, deleted_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE quizzes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  public_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  share_token CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status VARCHAR(10) COLLATE utf8mb4_bin NOT NULL DEFAULT 'draft',
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  cover_src VARCHAR(1000) NULL,
  time_limit_sec SMALLINT UNSIGNED NOT NULL DEFAULT 7200,
  ask_email TINYINT(1) NOT NULL DEFAULT 0,
  ask_phone TINYINT(1) NOT NULL DEFAULT 0,
  feedback VARCHAR(12) COLLATE utf8mb4_bin NOT NULL DEFAULT 'at_end',
  shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
  shuffle_options TINYINT(1) NOT NULL DEFAULT 0,
  draw_count SMALLINT UNSIGNED NULL,
  show_answers TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  deleted_at DATETIME(6) NULL,
  CONSTRAINT fk_quizzes_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT uq_quizzes_public_id UNIQUE (public_id),
  CONSTRAINT uq_quizzes_share_token UNIQUE (share_token),
  INDEX ix_quiz_user (user_id, status, deleted_at, updated_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  pos SMALLINT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  options_layout VARCHAR(8) COLLATE utf8mb4_bin NOT NULL DEFAULT 'list',
  media_type VARCHAR(8) COLLATE utf8mb4_bin NULL,
  media_src VARCHAR(1000) NULL,
  explanation TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
  CONSTRAINT uq_questions_quiz_pos UNIQUE (quiz_id, pos),
  CONSTRAINT uq_questions_id_quiz UNIQUE (id, quiz_id)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE question_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT UNSIGNED NOT NULL,
  pos SMALLINT UNSIGNED NOT NULL,
  code CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  content TEXT NOT NULL,
  media_type VARCHAR(8) COLLATE utf8mb4_bin NULL,
  media_src VARCHAR(1000) NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_question_options_question FOREIGN KEY (question_id) REFERENCES questions(id),
  CONSTRAINT uq_question_options_question_pos UNIQUE (question_id, pos),
  CONSTRAINT uq_question_options_question_code UNIQUE (question_id, code)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  public_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NULL,
  phone VARCHAR(32) NULL,
  ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL,
  snapshot JSON NOT NULL,
  answers JSON NOT NULL,
  answer_version INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(12) COLLATE utf8mb4_bin NOT NULL DEFAULT 'in_progress',
  started_at DATETIME(6) NOT NULL,
  last_saved_at DATETIME(6) NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  finished_at DATETIME(6) NULL,
  ended_reason VARCHAR(12) COLLATE utf8mb4_bin NULL,
  late_sync TINYINT(1) NOT NULL DEFAULT 0,
  score INT UNSIGNED NULL,
  max_score INT UNSIGNED NOT NULL,
  percent DECIMAL(5,2) NULL,
  CONSTRAINT fk_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
  CONSTRAINT uq_attempts_public_id UNIQUE (public_id),
  INDEX ix_attempt_report (quiz_id, status, started_at),
  INDEX ix_attempt_expiry (status, expires_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
