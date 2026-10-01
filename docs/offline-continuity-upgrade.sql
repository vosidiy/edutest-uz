-- NON-DESTRUCTIVE upgrade from the published-paper/normalized-answer schema.
-- Back up first. Pause student traffic and deploy the new application together
-- with this script. Run manually in phpMyAdmin; do not re-run it.
-- MySQL DDL implicitly commits. No attempt, answer, paper, or user is deleted.
ALTER TABLE attempts
  ADD COLUMN client_activity_at DATETIME(6) NULL AFTER last_activity_at,
  ADD COLUMN late_sync TINYINT(1) NOT NULL DEFAULT 0 AFTER client_activity_at,
  ADD CONSTRAINT chk_attempts_late_sync CHECK (late_sync IN (0, 1));

UPDATE attempts SET client_activity_at = last_activity_at;

ALTER TABLE attempts MODIFY COLUMN client_activity_at DATETIME(6) NOT NULL;

ALTER TABLE attempt_answers
  ADD COLUMN client_answered_at DATETIME(6) NULL AFTER answered_at;
-- Historical answer occurrence times remain NULL: receipt is not occurrence.
-- Fresh installations import only schema.sql, never this upgrade.
