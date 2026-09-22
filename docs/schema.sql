-- EduTest canonical application schema
-- Target: MySQL 8.4, InnoDB, utf8mb4
-- Last updated: 2026-09-22
--
-- Fresh-install use in phpMyAdmin:
--   1. Select the intended empty database.
--   2. Import this file once.
--
-- This is a complete fresh-install schema, not an upgrade script for an existing
-- populated database. Future changes must include separately supplied incremental
-- SQL for manual application in phpMyAdmin.

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
  share_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  mode VARCHAR(16) COLLATE utf8mb4_bin NOT NULL DEFAULT 'assessment',
  status VARCHAR(12) COLLATE utf8mb4_bin NOT NULL DEFAULT 'draft',
  listed TINYINT(1) NOT NULL DEFAULT 0,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  instructions TEXT NOT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  frozen_at DATETIME(6) NULL,
  time_limit_sec INT UNSIGNED NULL,
  opens_at DATETIME(6) NULL,
  closes_at DATETIME(6) NULL,
  passcode_hash VARCHAR(255) NULL,
  email_mode VARCHAR(8) COLLATE utf8mb4_bin NOT NULL DEFAULT 'optional',
  phone_mode VARCHAR(8) COLLATE utf8mb4_bin NOT NULL DEFAULT 'hidden',
  shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
  shuffle_options TINYINT(1) NOT NULL DEFAULT 0,
  feedback VARCHAR(12) COLLATE utf8mb4_bin NOT NULL DEFAULT 'at_end',
  show_score TINYINT(1) NOT NULL DEFAULT 1,
  show_answers TINYINT(1) NOT NULL DEFAULT 0,
  show_explain TINYINT(1) NOT NULL DEFAULT 0,
  cheat_check TINYINT(1) NOT NULL DEFAULT 0,
  practice_starts BIGINT UNSIGNED NOT NULL DEFAULT 0,
  published_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  deleted_at DATETIME(6) NULL,
  CONSTRAINT fk_quizzes_user
    FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT uq_quizzes_public_id UNIQUE (public_id),
  CONSTRAINT uq_quizzes_share_token UNIQUE (share_token),
  INDEX ix_quiz_user (user_id, status, deleted_at, updated_at),
  INDEX ix_quiz_listed (user_id, listed, status, deleted_at, published_at),
  CONSTRAINT chk_quizzes_mode
    CHECK (mode IN ('assessment', 'practice')),
  CONSTRAINT chk_quizzes_status
    CHECK (status IN ('draft', 'published', 'closed', 'archived')),
  CONSTRAINT chk_quizzes_feedback
    CHECK (feedback IN ('at_end', 'after_each')),
  CONSTRAINT chk_quizzes_listed
    CHECK (listed IN (0, 1)),
  CONSTRAINT chk_quizzes_shuffle_questions
    CHECK (shuffle_questions IN (0, 1)),
  CONSTRAINT chk_quizzes_shuffle_options
    CHECK (shuffle_options IN (0, 1)),
  CONSTRAINT chk_quizzes_show_score
    CHECK (show_score IN (0, 1)),
  CONSTRAINT chk_quizzes_show_answers
    CHECK (show_answers IN (0, 1)),
  CONSTRAINT chk_quizzes_show_explain
    CHECK (show_explain IN (0, 1)),
  CONSTRAINT chk_quizzes_cheat_check
    CHECK (cheat_check IN (0, 1)),
  CONSTRAINT chk_quizzes_email_mode
    CHECK (email_mode IN ('hidden', 'optional', 'required')),
  CONSTRAINT chk_quizzes_phone_mode
    CHECK (phone_mode IN ('hidden', 'optional', 'required')),
  CONSTRAINT chk_quizzes_time_limit
    CHECK (time_limit_sec IS NULL OR time_limit_sec BETWEEN 1 AND 86400),
  CONSTRAINT chk_quizzes_schedule
    CHECK (opens_at IS NULL OR closes_at IS NULL OR opens_at < closes_at),
  CONSTRAINT chk_quizzes_explanation_visibility
    CHECK (show_explain = 0 OR show_answers = 1),
  CONSTRAINT chk_quizzes_practice_settings
    CHECK (
      mode <> 'practice'
      OR (
        passcode_hash IS NULL
        AND email_mode = 'hidden'
        AND phone_mode = 'hidden'
        AND cheat_check = 0
      )
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  pos SMALLINT UNSIGNED NOT NULL,
  type VARCHAR(16) COLLATE utf8mb4_bin NOT NULL,
  content TEXT NOT NULL,
  media_type VARCHAR(8) COLLATE utf8mb4_bin NULL,
  media_src VARCHAR(1000) NULL,
  explanation TEXT NULL,
  points DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  time_limit_sec INT UNSIGNED NULL,
  text_answers JSON NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_questions_quiz
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
  CONSTRAINT uq_questions_quiz_pos UNIQUE (quiz_id, pos),
  CONSTRAINT uq_questions_id_quiz UNIQUE (id, quiz_id),
  CONSTRAINT chk_questions_type
    CHECK (type IN ('single_choice', 'multi_select', 'short_text')),
  CONSTRAINT chk_questions_pos
    CHECK (pos > 0),
  CONSTRAINT chk_questions_points
    CHECK (points > 0 AND points <= 10000),
  CONSTRAINT chk_questions_time_limit
    CHECK (time_limit_sec IS NULL OR time_limit_sec BETWEEN 1 AND 86400),
  CONSTRAINT chk_questions_media_type
    CHECK (media_type IS NULL OR media_type IN ('image', 'audio', 'video')),
  CONSTRAINT chk_questions_media_pair
    CHECK (
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
  CONSTRAINT fk_question_options_question
    FOREIGN KEY (question_id) REFERENCES questions(id),
  CONSTRAINT uq_question_options_question_pos UNIQUE (question_id, pos),
  CONSTRAINT uq_question_options_question_code UNIQUE (question_id, code),
  CONSTRAINT chk_question_options_pos
    CHECK (pos > 0),
  CONSTRAINT chk_question_options_correct
    CHECK (is_correct IN (0, 1)),
  CONSTRAINT chk_question_options_media_type
    CHECK (media_type IS NULL OR media_type IN ('image', 'audio', 'video')),
  CONSTRAINT chk_question_options_media_pair
    CHECK (
      (media_type IS NULL AND media_src IS NULL)
      OR (media_type IS NOT NULL AND media_src IS NOT NULL)
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  revision INT UNSIGNED NOT NULL,
  public_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash BINARY(32) NOT NULL,
  start_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  start_hash BINARY(32) NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NULL,
  phone VARCHAR(32) NULL,
  ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL,
  agent VARCHAR(512) NULL,
  status VARCHAR(12) COLLATE utf8mb4_bin NOT NULL DEFAULT 'in_progress',
  phase VARCHAR(20) COLLATE utf8mb4_bin NOT NULL DEFAULT 'answering',
  current_pos SMALLINT UNSIGNED NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  settings JSON NOT NULL,
  started_at DATETIME(6) NOT NULL,
  total_due_at DATETIME(6) NULL,
  close_at DATETIME(6) NULL,
  due_at DATETIME(6) NULL,
  submitted_at DATETIME(6) NULL,
  finish_reason VARCHAR(20) COLLATE utf8mb4_bin NULL,
  score DECIMAL(12,2) NULL,
  max_score DECIMAL(12,2) NOT NULL,
  percent DECIMAL(5,2) NULL,
  updated_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_attempts_quiz
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
  CONSTRAINT uq_attempts_public_id UNIQUE (public_id),
  CONSTRAINT uq_attempts_token_hash UNIQUE (token_hash),
  CONSTRAINT uq_attempts_quiz_start_key UNIQUE (quiz_id, start_key),
  CONSTRAINT uq_attempts_id_quiz UNIQUE (id, quiz_id),
  INDEX ix_attempt_report (quiz_id, status, started_at),
  INDEX ix_attempt_due (status, due_at),
  CONSTRAINT chk_attempts_status
    CHECK (status IN ('in_progress', 'submitted', 'expired')),
  CONSTRAINT chk_attempts_phase
    CHECK (phase IN ('answering', 'feedback', 'awaiting_next', 'complete')),
  CONSTRAINT chk_attempts_finish_reason
    CHECK (
      finish_reason IS NULL
      OR finish_reason IN ('completed', 'total_timeout', 'scheduled_close')
    ),
  CONSTRAINT chk_attempts_max_score
    CHECK (max_score > 0),
  CONSTRAINT chk_attempts_score
    CHECK (score IS NULL OR (score >= 0 AND score <= max_score)),
  CONSTRAINT chk_attempts_percent
    CHECK (percent IS NULL OR percent BETWEEN 0 AND 100)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE attempt_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  quiz_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  pos SMALLINT UNSIGNED NOT NULL,
  choice_order JSON NOT NULL,
  status VARCHAR(8) COLLATE utf8mb4_bin NOT NULL DEFAULT 'pending',
  started_at DATETIME(6) NULL,
  due_at DATETIME(6) NULL,
  locked_at DATETIME(6) NULL,
  lock_reason VARCHAR(20) COLLATE utf8mb4_bin NULL,
  answer_codes JSON NULL,
  text_answer VARCHAR(500) NULL,
  save_ver INT UNSIGNED NOT NULL DEFAULT 0,
  saved_at DATETIME(6) NULL,
  submit_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
  submit_hash BINARY(32) NULL,
  result VARCHAR(12) COLLATE utf8mb4_bin NULL,
  points DECIMAL(8,2) NULL,
  CONSTRAINT fk_attempt_items_attempt
    FOREIGN KEY (attempt_id, quiz_id) REFERENCES attempts(id, quiz_id),
  CONSTRAINT fk_attempt_items_question
    FOREIGN KEY (question_id, quiz_id) REFERENCES questions(id, quiz_id),
  CONSTRAINT uq_attempt_items_attempt_pos UNIQUE (attempt_id, pos),
  CONSTRAINT uq_attempt_items_attempt_question UNIQUE (attempt_id, question_id),
  CONSTRAINT uq_attempt_items_attempt_submit_key UNIQUE (attempt_id, submit_key),
  INDEX ix_item_due (status, due_at),
  CONSTRAINT chk_attempt_items_pos
    CHECK (pos > 0),
  CONSTRAINT chk_attempt_items_status
    CHECK (status IN ('pending', 'active', 'locked')),
  CONSTRAINT chk_attempt_items_lock_reason
    CHECK (
      lock_reason IS NULL
      OR lock_reason IN ('answered', 'skipped', 'question_timeout', 'attempt_timeout')
    ),
  CONSTRAINT chk_attempt_items_result
    CHECK (result IS NULL OR result IN ('correct', 'partial', 'wrong', 'unanswered')),
  CONSTRAINT chk_attempt_items_points
    CHECK (points IS NULL OR points >= 0),
  CONSTRAINT chk_attempt_items_submit_pair
    CHECK (
      (submit_key IS NULL AND submit_hash IS NULL)
      OR (submit_key IS NOT NULL AND submit_hash IS NOT NULL)
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE cheat_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  event_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  type VARCHAR(24) COLLATE utf8mb4_bin NOT NULL,
  happened_at DATETIME(6) NULL,
  received_at DATETIME(6) NOT NULL,
  duration_ms INT UNSIGNED NULL,
  data JSON NOT NULL,
  CONSTRAINT fk_cheat_events_attempt
    FOREIGN KEY (attempt_id) REFERENCES attempts(id),
  CONSTRAINT uq_cheat_events_attempt_event_key UNIQUE (attempt_id, event_key),
  INDEX ix_cheat_timeline (attempt_id, received_at),
  CONSTRAINT chk_cheat_events_type
    CHECK (
      type IN (
        'tab_hidden',
        'tab_visible',
        'window_blur',
        'window_focus',
        'fullscreen_exit',
        'inactivity_start',
        'inactivity_end'
      )
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE practice_keys (
  quiz_id BIGINT UNSIGNED NOT NULL,
  request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  CONSTRAINT pk_practice_keys PRIMARY KEY (quiz_id, request_key),
  INDEX ix_practice_expiry (expires_at),
  CONSTRAINT fk_practice_keys_quiz
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
