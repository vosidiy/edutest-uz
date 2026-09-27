# Student player

The public introduction is `/q/{shareToken}`; player and student results use `/play` and `/results` below that link. Credentials are kept in same-tab `sessionStorage`, never URLs. Starting requires a network connection. After starting, answering and feedback do not wait for the server.

## Database and configuration

The canonical schema includes immutable paper schema version 2, equal-weight question credit, `attempt_items.credit`, image-only answer-option media, `attempts.paper_id`, `practice_keys.paper_id`, historical attempt question identifiers, `quizzes.first_started_at`, the optional cover, and the late-sync flag. The developer reported applying the one-time quiz-simplification SQL on 2026-09-27, so its incremental script has been removed. Fresh databases should import only [`schema.sql`](schema.sql), which remains the complete source of truth. The application and agent do not execute database upgrades.

An untracked `encryption.key` is required for signed admission and media. Keep PHP upload limits high enough for the existing image/audio limits. Cover images accept JPEG/PNG/WebP up to 5 MiB and 16 megapixels. Application code avoids logging student bodies; configure hosting/proxy access logs to redact signed media URLs and credentials separately.

## Student API

All routes are under `/api/v1/player`, require `X-EduTest-Player: 1`, and use same-origin requests with cookies omitted. POST bodies are JSON objects. Standard `{data, meta}` / `{error, meta}` envelopes use UTC ISO timestamps, decimal strings, and string database IDs; student metadata does not initialize session CSRF.

| Method and endpoint | Credential and behavior |
| --- | --- |
| `GET tickets/{shareToken}` | Public availability check; signed 15-minute admission ticket and assessment recovery credential. |
| `POST starts` | Admission ticket plus configured assessment identity/passcode; practice accepts the ticket only. Retries reuse the same start. |
| `GET assessments/{attemptId}` | Assessment bearer; starting policy, immutable paper definition, persisted order, saved progress, and any finalized result. |
| `POST assessments/{attemptId}/sync` | Assessment bearer; aggregate `version`, up to 100 ordered changed `items`, optional `finishReason`. |
| `GET assessments/{attemptId}/results` | Assessment bearer; only finalized results. |
| `POST assessments/{attemptId}/events` | Assessment bearer with captured monitoring enabled; up to 100 idempotent integrity events. |
| `POST assessments/{attemptId}/media` | Assessment bearer; renew current owned media descriptors. |
| `POST practice/media` | Signed practice bearer and empty JSON object; renew media/ticket without posting progress. |

Sync items contain `questionId`, `saveVer`, `startedAt`, `answerCodes`, `textAnswer`, nullable `submitKey`, and `reason`. Only a prefix of submitted questions may advance. Submission keys and answers become immutable when acknowledged; duplicate acknowledgements do not increment the version. `finishReason` is `completed`, `total_timeout`, or `scheduled_close`.

## Important limitations

A start creates or reuses one immutable paper for the current quiz revision and binds the assessment attempt or practice key to it. Teachers may edit or delete live questions afterward. Existing synchronization, scoring, results, media renewal, and teacher review use the bound paper; new starts use the newest saved revision. The builder locks only the assessment/practice mode after first use.

Completed practice runs offer **Try again**, including timed-out runs. This requests a fresh admission/start, rechecks availability, captures current settings and configured shuffling, resets progress/timers, and increments the aggregate start count once. A connection is required. Failed requests preserve the previous result and reuse pending admission for retry, including after refresh; no identity, answers, or results are submitted. Assessments do not show this action.

Teachers may optionally select a cover in the quiz-creation dialog. The draft is created before its cover is uploaded. If uploading fails, retry uses the same draft, or the teacher can continue without a cover. Cancelling after draft creation leaves that draft saved in My quizzes. Cancel, close, and Escape bypass title validation when idle; dismissal is temporarily disabled during a save/upload.

The builder has one Add question action and supports single choice, multi-select, and short text. Answer choices are lettered in presented order and accept images only; question-level media still supports image, audio, and validated video. Total time is entered in half-minute steps and stored in seconds internally. Questions have equal weight and no individual timers.

- Both feedback modes download keys at start. `correctCodes` and accepted text answers are readable in browser tools. Option codes are identifiers, not encryption or anti-cheating protection.
- `showScore`, `showAnswers`, and `showExplain` govern presentation. Explanations are included only when enabled; correctness appears at the configured feedback time.
- Assessment scores are recalculated from the attempt's bound paper keys. Every question has maximum credit `1.00`; multi-select retains proportional partial credit. Results are shown as equivalent correct questions plus percentage, not points. Browser results remain provisional until synchronization succeeds. Practice sends no answers/results and has no official saved score.
- Start and receipt timestamps are server-generated. Total/closing timers remain browser-enforced during offline work and cannot be independently verified. Late work is accepted with a timing flag; this is not a strict examination timer. There are no per-question timers.
- Closing, archiving, or soft deletion blocks new starts, not completion of existing authorized attempts. Existing attempts keep their starting settings. Abandoned attempts are not automatically finalized while offline work may remain.
- Refresh recovery requires tab storage and a reachable page. If storage is unavailable/full, keep the page open. Closing the tab may lose unsynchronized work. Only already-loaded media can be expected during a disconnect; external video requires connectivity.
- Advanced report analytics, student accounts, attempt limits, and standalone offline exports are deferred.

## Verification

Run with PHP 8.4 and the test configuration, never the development database:

```sh
php vendor/bin/phpunit --no-coverage
node --test tests/js/player.test.mjs
node tests/routes-check.mjs
node tests/browser/player-smoke.mjs
```

Player service/feature tests create a fresh in-memory SQLite database from a test-only adaptation of the canonical schema. They do not connect to the imported MAMP database. MySQL-specific lock contention still needs a dedicated MySQL test environment before production load testing.

`routes-check.mjs` invokes the real `spark routes` and `spark filter:check` commands with CI4's test support path and testing environment. The browser check uses actual views/assets, an isolated loopback fixture API, and a temporary Chrome profile; it covers three widths, offline completion, synchronization, all 12 valid feedback/visibility combinations, conflict focus/recovery, refresh, lost start acknowledgements, and unavailable storage. Set `PLAYER_PHP` / `PLAYER_CHROME` to your local executable paths as needed. Node is test tooling only, not an application/runtime dependency.

Check desktop/tablet/mobile introduction, identity validation, every question type, both feedback modes, all visibility combinations, total/scheduled timer warnings and timeouts, offline/online recovery, retry/conflict dialogs, lettered choices, media renewal, and cover controls. Verify keyboard-only use, visible focus, status announcements, contrast, reduced motion, and touch targets. Do not treat a browser fixture run as a live MAMP/database integration check.

### Remaining deployment verification

- Verify the private signing key configuration and confirm the database matches the current canonical [`schema.sql`](schema.sql). Fresh or disposable development databases should be recreated from that schema. No database is modified automatically by the application or test tooling.
- Exercise the full teacher-to-student workflow on the local host after upgrading, including real cover/answer-image/question-audio/question-video uploads and renewal.
- Run concurrent start/save/media workflows against an isolated MySQL 8.4 database. SQLite rollback tests and simulated deadlock retries do not prove InnoDB concurrency behavior.
- Complete real-device/Safari and assistive-technology checks. Chrome fixture checks cover responsive layout, keyboard focus, live-region markup, and reduced motion, but are not a full screen-reader audit.
