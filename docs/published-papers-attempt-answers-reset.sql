-- EduTest destructive development reset: published papers + normalized answers
-- Target: the immediately preceding schema on MySQL 8.4.
--
-- IMPORTANT
--   * Back up the database first.
--   * Stop teacher and student traffic while this file and the matching PHP/JS
--     code are deployed.
--   * This intentionally deletes every user, quiz, question, option, paper,
--     attempt, integrity event, and Practice key. Existing browser credentials
--     and progress become invalid.
--   * Private files below writable/ are not removed by SQL. Delete obsolete
--     development uploads separately if desired.
--   * Do not run this after importing the current docs/schema.sql into an empty
--     database; that schema already contains the final structure.

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS cheat_events;
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS practice_keys;
DROP TABLE IF EXISTS quiz_papers;

DELETE FROM question_options;
DELETE FROM questions;
DELETE FROM quizzes;
DELETE FROM users;

ALTER TABLE question_options AUTO_INCREMENT = 1;
ALTER TABLE questions AUTO_INCREMENT = 1;
ALTER TABLE quizzes AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

ALTER TABLE quizzes
  DROP COLUMN first_started_at,
  ADD COLUMN current_paper_id BIGINT UNSIGNED NULL AFTER version,
  ADD INDEX ix_quiz_current_paper (current_paper_id, id);

CREATE TABLE quiz_papers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  public_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision INT UNSIGNED NOT NULL,
  passcode_hash VARCHAR(255) NULL,
  definition JSON NOT NULL,
  created_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_quiz_papers_quiz
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
  CONSTRAINT uq_quiz_papers_public_id UNIQUE (public_id),
  CONSTRAINT uq_quiz_papers_quiz_revision UNIQUE (quiz_id, revision),
  CONSTRAINT uq_quiz_papers_id_quiz UNIQUE (id, quiz_id),
  CONSTRAINT chk_quiz_papers_revision CHECK (revision > 0)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE quizzes
  ADD CONSTRAINT fk_quizzes_current_paper
    FOREIGN KEY (current_paper_id, id) REFERENCES quiz_papers(id, quiz_id);

CREATE TABLE attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  paper_id BIGINT UNSIGNED NOT NULL,
  public_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash BINARY(32) NOT NULL,
  start_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  shuffle_seed CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NULL,
  phone VARCHAR(32) NULL,
  ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL,
  agent VARCHAR(512) NULL,
  status VARCHAR(12) COLLATE utf8mb4_bin NOT NULL DEFAULT 'in_progress',
  started_at DATETIME(6) NOT NULL,
  last_activity_at DATETIME(6) NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  deadline_reason VARCHAR(20) COLLATE utf8mb4_bin NOT NULL,
  finished_at DATETIME(6) NULL,
  ended_reason VARCHAR(20) COLLATE utf8mb4_bin NULL,
  score INT UNSIGNED NULL,
  max_score INT UNSIGNED NOT NULL,
  percent DECIMAL(5,2) NULL,
  CONSTRAINT fk_attempts_quiz
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
  CONSTRAINT fk_attempts_paper
    FOREIGN KEY (paper_id, quiz_id) REFERENCES quiz_papers(id, quiz_id),
  CONSTRAINT uq_attempts_public_id UNIQUE (public_id),
  CONSTRAINT uq_attempts_token_hash UNIQUE (token_hash),
  CONSTRAINT uq_attempts_quiz_start_key UNIQUE (quiz_id, start_key),
  CONSTRAINT uq_attempts_id_quiz UNIQUE (id, quiz_id),
  INDEX ix_attempt_report (quiz_id, status, started_at),
  INDEX ix_attempt_expiry (status, expires_at),
  CONSTRAINT chk_attempts_status
    CHECK (status IN ('in_progress', 'completed', 'abandoned')),
  CONSTRAINT chk_attempts_deadline_reason
    CHECK (deadline_reason IN ('timer_expired', 'scheduled_close', 'stale_timeout')),
  CONSTRAINT chk_attempts_ended_reason
    CHECK (
      ended_reason IS NULL
      OR ended_reason IN ('completed', 'timer_expired', 'scheduled_close', 'stale_timeout')
    ),
  CONSTRAINT chk_attempts_terminal_fields
    CHECK (
      (status = 'in_progress' AND finished_at IS NULL AND ended_reason IS NULL AND score IS NULL AND percent IS NULL)
      OR (status IN ('completed', 'abandoned') AND finished_at IS NOT NULL AND ended_reason IS NOT NULL AND score IS NOT NULL AND percent IS NOT NULL)
    ),
  CONSTRAINT chk_attempts_max_score CHECK (max_score > 0),
  CONSTRAINT chk_attempts_score CHECK (score IS NULL OR (score >= 0 AND score <= max_score)),
  CONSTRAINT chk_attempts_percent CHECK (percent IS NULL OR percent BETWEEN 0 AND 100)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE attempt_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  pos SMALLINT UNSIGNED NOT NULL,
  presented_option_codes JSON NOT NULL,
  status VARCHAR(12) COLLATE utf8mb4_bin NOT NULL DEFAULT 'not_reached',
  selected_option_codes JSON NULL,
  text_answer VARCHAR(500) NULL,
  is_correct TINYINT(1) NULL,
  answered_at DATETIME(6) NULL,
  CONSTRAINT fk_attempt_answers_attempt
    FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  CONSTRAINT uq_attempt_answers_question UNIQUE (attempt_id, question_id),
  CONSTRAINT uq_attempt_answers_position UNIQUE (attempt_id, pos),
  CONSTRAINT chk_attempt_answers_position CHECK (pos > 0),
  CONSTRAINT chk_attempt_answers_presented CHECK (JSON_TYPE(presented_option_codes) = 'ARRAY'),
  CONSTRAINT chk_attempt_answers_selected CHECK (selected_option_codes IS NULL OR JSON_TYPE(selected_option_codes) = 'ARRAY'),
  CONSTRAINT chk_attempt_answers_status CHECK (status IN ('not_reached', 'answered', 'skipped')),
  CONSTRAINT chk_attempt_answers_state
    CHECK (
      (status = 'not_reached' AND selected_option_codes IS NULL AND text_answer IS NULL AND is_correct IS NULL AND answered_at IS NULL)
      OR (status = 'skipped' AND selected_option_codes IS NULL AND text_answer IS NULL AND is_correct = 0 AND answered_at IS NOT NULL)
      OR (
        status = 'answered'
        AND (
          (selected_option_codes IS NOT NULL AND JSON_LENGTH(selected_option_codes) > 0 AND text_answer IS NULL)
          OR (selected_option_codes IS NULL AND text_answer IS NOT NULL AND CHAR_LENGTH(text_answer) > 0)
        )
        AND is_correct IN (0, 1)
        AND answered_at IS NOT NULL
      )
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
  CONSTRAINT chk_cheat_events_type CHECK (type IN ('tab_hidden', 'fullscreen_exit'))
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE practice_keys (
  quiz_id BIGINT UNSIGNED NOT NULL,
  paper_id BIGINT UNSIGNED NOT NULL,
  request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  CONSTRAINT pk_practice_keys PRIMARY KEY (quiz_id, request_key),
  INDEX ix_practice_expiry (expires_at),
  CONSTRAINT fk_practice_keys_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
  CONSTRAINT fk_practice_keys_paper
    FOREIGN KEY (paper_id, quiz_id) REFERENCES quiz_papers(id, quiz_id)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Expected final checks:
--   SHOW COLUMNS FROM quizzes;
--   SHOW COLUMNS FROM attempts;
--   SHOW CREATE TABLE quiz_papers;
--   SHOW CREATE TABLE attempt_answers;
