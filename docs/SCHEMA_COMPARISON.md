# Schema comparison — 2026-11-16

Compared `docs/schema.sql` with the CREATE TABLE and ALTER TABLE statements in `docs/db-backup.sql`. INSERT data was excluded from the structural comparison; the backup was not edited.

## Result

| Structure | Result after alignment |
| --- | --- |
| Tables | Same 5: users, quizzes, questions, question_options, attempts |
| Columns | Same 76, including order, types, unsigned attributes, nullability, and defaults |
| Primary/unique keys and secondary indexes | Same 18 declared definitions, including names and column order |
| Auto-increment | Same: id on all five tables |
| Foreign keys | Same 4, including names and referenced columns; no explicit cascade actions in either file |
| CHECK constraints | Backup has none; removed the canonical schema's 33 extra checks |
| Explicit column character settings | Same; the backup explicitly repeats utf8mb4 on some columns where the schema inherits it |
| Engine and default charset/collation | Backup omits them; canonical schema retains InnoDB, utf8mb4, utf8mb4_0900_ai_ci |

The SQL formatting differs: the backup adds keys and auto-increment with ALTER TABLE, while the canonical schema declares them inline. Explicit DEFAULT NULL and quoted numeric defaults in the backup are equivalent to the canonical declarations. INSERT statements and current auto-increment counters are data, not fresh-install schema requirements.

**Full physical equality cannot be established from this backup.** Its CREATE TABLE statements end with `);` and contain no ENGINE/default charset/default collation options. The inherited character settings were compared assuming the canonical utf8mb4 defaults. To confirm the source defaults, run these read-only queries in phpMyAdmin on the source database:

```sql
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'quizzes', 'questions', 'question_options', 'attempts')
ORDER BY TABLE_NAME;

SHOW CREATE TABLE users;
SHOW CREATE TABLE quizzes;
SHOW CREATE TABLE questions;
SHOW CREATE TABLE question_options;
SHOW CREATE TABLE attempts;
```

Use a new complete export with table options if those results need to be preserved exactly. The dump identifies its source as MySQL 8.0.44; the project's documented target remains MySQL 8.4. Namecheap's assigned MariaDB version still needs separate import and runtime verification; see [deployment checklist](DEPLOYMENT.md).

## Effect on validation

Removing the checks matches the supplied database export. It also removes database-level validation of boolean ranges, public-page/username consistency, reset-field pairing, quiz status/timer settings, media pairing/types, question/option positions, JSON object/array shape, and attempt status/scoring relationships. Application services continue to validate normal writes. Direct SQL writes must preserve those rules themselves. Column types, NOT NULL, unique keys, and foreign keys are retained; MySQL JSON columns still require valid JSON syntax.

## Existing databases: optional incremental MySQL SQL

A database already matching `db-backup.sql` needs **no SQL update** for this alignment. Do not run these statements against it: the named checks do not exist there.

For a MySQL 8 installation created from the immediately preceding canonical schema, the exact forward SQL is below. Back up first and inspect SHOW CREATE TABLE for each table. Run only DROP clauses for checks that actually exist. If all 33 checks exist, run the block as written in phpMyAdmin against the selected existing database. It removes constraints only and preserves rows/columns/keys. MySQL DDL implicitly commits; this is not transactionally reversible. Do not use this block as an unreviewed MariaDB upgrade script.

```sql
ALTER TABLE `users`
  DROP CHECK `chk_users_username`,
  DROP CHECK `chk_users_public_page`,
  DROP CHECK `chk_users_public_page_username`,
  DROP CHECK `chk_users_active`,
  DROP CHECK `chk_users_reset_pair`;

ALTER TABLE `quizzes`
  DROP CHECK `chk_quizzes_status`,
  DROP CHECK `chk_quizzes_feedback`,
  DROP CHECK `chk_quizzes_ask_email`,
  DROP CHECK `chk_quizzes_ask_phone`,
  DROP CHECK `chk_quizzes_shuffle_questions`,
  DROP CHECK `chk_quizzes_shuffle_options`,
  DROP CHECK `chk_quizzes_show_answers`,
  DROP CHECK `chk_quizzes_time_limit`,
  DROP CHECK `chk_quizzes_draw_count`;

ALTER TABLE `questions`
  DROP CHECK `chk_questions_pos`,
  DROP CHECK `chk_questions_options_layout`,
  DROP CHECK `chk_questions_media_type`,
  DROP CHECK `chk_questions_media_pair`;

ALTER TABLE `question_options`
  DROP CHECK `chk_question_options_pos`,
  DROP CHECK `chk_question_options_correct`,
  DROP CHECK `chk_question_options_media_type`,
  DROP CHECK `chk_question_options_media_pair`;

ALTER TABLE `attempts`
  DROP CHECK `chk_attempts_snapshot`,
  DROP CHECK `chk_attempts_answers`,
  DROP CHECK `chk_attempts_status`,
  DROP CHECK `chk_attempts_ended_reason`,
  DROP CHECK `chk_attempts_late_sync`,
  DROP CHECK `chk_attempts_terminal_fields`,
  DROP CHECK `chk_attempts_abandoned_reason`,
  DROP CHECK `chk_attempts_max_score`,
  DROP CHECK `chk_attempts_score`,
  DROP CHECK `chk_attempts_percent`,
  DROP CHECK `chk_attempts_score_percent`;
```

Never import `schema.sql` over an existing database. For a new empty database, choose either the canonical schema (empty application) or a complete backup (existing data), not both.

## Verification performed

- Static normalized DDL comparison: all five tables, 76 columns, 18 declared keys/indexes, and four foreign keys match, subject to the omitted table-option limitation above. The comparison included column order, defaults, nullability, unsigned types, auto-increment, and declared character settings.
- Focused schema PHPUnit test passed: 1 test, 41 assertions.
- Unit and service PHPUnit tests passed: 48 tests, 344 assertions, using isolated in-memory SQLite fixtures.
- Full PHPUnit suite ran: 117 tests, 880 assertions, two failures in unrelated view assertions. The login test expects an `h2` but the current view uses `h4`; the landing test expects `images/landing/placeholder.jpg`, which the current page does not contain. Those views and expectations were left unchanged.
- An isolated MySQL 8.0.44 server initialization was attempted under a temporary directory, but the local MAMP mysqld binary crashed before startup. Neither SQL file was imported into MySQL; no development/production database was changed.
- No hosting deployment or visual/responsive checks were performed.
