-- EduTest: short quiz codes and focused integrity events (MySQL 8.4).
-- MANUAL, ONE-TIME upgrade for an existing current-schema database.
-- Back up first and briefly stop teacher/student activity before running.
-- Deploy the matching application code before reopening the site.
-- Existing 64-character share links remain unchanged and valid.
-- Historical tab-hidden/fullscreen-exit events are preserved; obsolete event types are deleted.
-- If attempt-responses-upgrade.sql is also pending, run it first and this script second.
-- Fresh databases: import schema.sql only; do not run this file.
-- MySQL DDL implicitly commits: the whole script cannot be rolled back as one unit.

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+00:00';

ALTER TABLE quizzes
  MODIFY COLUMN share_token VARCHAR(64)
    CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

DELETE FROM cheat_events
WHERE type NOT IN ('tab_hidden', 'fullscreen_exit');

ALTER TABLE cheat_events
  DROP CHECK chk_cheat_events_type,
  ADD CONSTRAINT chk_cheat_events_type
    CHECK (type IN ('tab_hidden', 'fullscreen_exit'));
