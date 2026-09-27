-- EduTest: consolidate assessment answers into attempts.responses (MySQL 8.4).
-- DESTRUCTIVE, MANUAL, ONE-TIME upgrade from the equal-weight paper schema.
-- Back up first. Stop teacher/student activity and background workers before running.
-- Deploy the matching application code before reopening the site.
-- Deletes all assessment attempts, their answers, and integrity events.
-- Preserves users, quizzes, questions/options, papers/media, practice keys/counters,
-- first_started_at and all other quiz settings. Does not reset AUTO_INCREMENT.
-- Fresh databases: import schema.sql only; do not run this file.
-- MySQL DDL implicitly commits: the whole script cannot be rolled back as one unit.

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+00:00';

START TRANSACTION;
DELETE FROM cheat_events;
DELETE FROM attempt_items;
DELETE FROM attempts;
COMMIT;

ALTER TABLE attempts
  ADD COLUMN responses JSON NOT NULL AFTER settings,
  ADD CONSTRAINT chk_attempts_responses CHECK (JSON_TYPE(responses) = 'OBJECT');

DROP TABLE attempt_items;
