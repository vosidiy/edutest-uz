-- 123test canonical application schema
-- Target: MySQL 8.4, InnoDB, utf8mb4
-- Last updated: 2026-10-31
--
-- Fresh-install use in phpMyAdmin:
--   1. Select the intended empty database.
--   2. Import this file once.
--
-- This is a complete fresh-install schema, not an upgrade script.

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+00:00';

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  display_name VARCHAR(120) NOT NULL,
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
  INDEX ix_users_public (public_page, active, deleted_at),
  CONSTRAINT chk_users_public_page CHECK (public_page IN (0, 1)),
  CONSTRAINT chk_users_active CHECK (active IN (0, 1)),
  CONSTRAINT chk_users_reset_pair CHECK (
    (password_reset_hash IS NULL AND password_reset_expires_at IS NULL)
    OR (password_reset_hash IS NOT NULL AND password_reset_expires_at IS NOT NULL)
  )
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
  show_score TINYINT(1) NOT NULL DEFAULT 1,
  show_answers TINYINT(1) NOT NULL DEFAULT 0,
  show_explain TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  deleted_at DATETIME(6) NULL,
  CONSTRAINT fk_quizzes_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT uq_quizzes_public_id UNIQUE (public_id),
  CONSTRAINT uq_quizzes_share_token UNIQUE (share_token),
  INDEX ix_quiz_user (user_id, status, deleted_at, updated_at),
  CONSTRAINT chk_quizzes_status CHECK (status IN ('draft', 'published')),
  CONSTRAINT chk_quizzes_feedback CHECK (feedback IN ('at_end', 'after_each')),
  CONSTRAINT chk_quizzes_ask_email CHECK (ask_email IN (0, 1)),
  CONSTRAINT chk_quizzes_ask_phone CHECK (ask_phone IN (0, 1)),
  CONSTRAINT chk_quizzes_shuffle_questions CHECK (shuffle_questions IN (0, 1)),
  CONSTRAINT chk_quizzes_shuffle_options CHECK (shuffle_options IN (0, 1)),
  CONSTRAINT chk_quizzes_show_score CHECK (show_score IN (0, 1)),
  CONSTRAINT chk_quizzes_show_answers CHECK (show_answers IN (0, 1)),
  CONSTRAINT chk_quizzes_show_explain CHECK (show_explain IN (0, 1)),
  CONSTRAINT chk_quizzes_time_limit CHECK (
    time_limit_sec BETWEEN 30 AND 7200 AND MOD(time_limit_sec, 30) = 0
  ),
  CONSTRAINT chk_quizzes_draw_count CHECK (draw_count IS NULL OR draw_count > 0),
  CONSTRAINT chk_quizzes_explanation_visibility CHECK (show_explain = 0 OR show_answers = 1)
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
  CONSTRAINT uq_questions_id_quiz UNIQUE (id, quiz_id),
  CONSTRAINT chk_questions_pos CHECK (pos > 0),
  CONSTRAINT chk_questions_options_layout CHECK (options_layout IN ('list', 'grid')),
  CONSTRAINT chk_questions_media_type CHECK (media_type IS NULL OR media_type IN ('image', 'audio')),
  CONSTRAINT chk_questions_media_pair CHECK (
    (media_type IS NULL AND media_src IS NULL)
    OR (media_type IS NOT NULL AND media_src IS NOT NULL)
  )
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
  CONSTRAINT uq_question_options_question_code UNIQUE (question_id, code),
  CONSTRAINT chk_question_options_pos CHECK (pos > 0),
  CONSTRAINT chk_question_options_correct CHECK (is_correct IN (0, 1)),
  CONSTRAINT chk_question_options_media_type CHECK (media_type IS NULL OR media_type = 'image'),
  CONSTRAINT chk_question_options_media_pair CHECK (
    (media_type IS NULL AND media_src IS NULL)
    OR (media_type IS NOT NULL AND media_src IS NOT NULL)
  )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  public_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash BINARY(32) NOT NULL,
  start_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NULL,
  phone VARCHAR(32) NULL,
  ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL,
  agent VARCHAR(512) NULL,
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
  CONSTRAINT uq_attempts_token_hash UNIQUE (token_hash),
  CONSTRAINT uq_attempts_quiz_start_key UNIQUE (quiz_id, start_key),
  INDEX ix_attempt_report (quiz_id, status, started_at),
  INDEX ix_attempt_expiry (status, expires_at),
  CONSTRAINT chk_attempts_snapshot CHECK (JSON_TYPE(snapshot) = 'OBJECT'),
  CONSTRAINT chk_attempts_answers CHECK (JSON_TYPE(answers) = 'ARRAY'),
  CONSTRAINT chk_attempts_status CHECK (status IN ('in_progress', 'completed', 'abandoned')),
  CONSTRAINT chk_attempts_ended_reason CHECK (
    ended_reason IS NULL OR ended_reason IN ('completed', 'exited', 'expired')
  ),
  CONSTRAINT chk_attempts_late_sync CHECK (late_sync IN (0, 1)),
  CONSTRAINT chk_attempts_terminal_fields CHECK (
    (status = 'in_progress' AND finished_at IS NULL AND ended_reason IS NULL AND score IS NULL AND percent IS NULL)
    OR (status IN ('completed', 'abandoned') AND finished_at IS NOT NULL AND ended_reason IS NOT NULL AND score IS NOT NULL AND percent IS NOT NULL)
  ),
  CONSTRAINT chk_attempts_abandoned_reason CHECK (status <> 'abandoned' OR ended_reason = 'expired'),
  CONSTRAINT chk_attempts_max_score CHECK (max_score > 0),
  CONSTRAINT chk_attempts_score CHECK (score IS NULL OR score <= max_score),
  CONSTRAINT chk_attempts_percent CHECK (percent IS NULL OR percent BETWEEN 0 AND 100),
  CONSTRAINT chk_attempts_score_percent CHECK (
    (score IS NULL AND percent IS NULL)
    OR (score IS NOT NULL AND percent = ROUND(score * 100 / max_score, 2))
  )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
