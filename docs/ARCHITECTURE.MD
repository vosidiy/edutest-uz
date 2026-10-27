# 123test architecture

## Platform

123test is a server-rendered CodeIgniter 4 application running on PHP 8.4 and MySQL 8.4. It uses plain JavaScript/CSS for the student player and a pinned local Vue 3 production build for the quiz builder. There is no Node/Vite production runtime or SPA router.

`public/` is the web root. Runtime data and quiz media live under `writable/`. Environment values and secrets belong in the untracked root `.env`.

Controllers parse HTTP input and return responses. Services own validation, transactions, scoring, and state changes. Models provide narrow persistence. Explicit routes are used with automatic routing disabled.

## Data model

`docs/schema.sql` is the complete fresh-install source of truth. The project does not use migrations.

### `quizzes`

Stores the teacher-owned live definition and its lifecycle. `version` is the optimistic-lock value. `public_id` is the reporting/builder identifier; the nine-digit `share_token` locates the public quiz. Status is only `draft` or `published`; `deleted_at` provides trash/restore without erasing reports.

Every quiz has a 30–7,200 second timer in 30-second steps. `draw_count` is null for the entire pool or a positive subset size. Published saves are allowed only when the resulting quiz is publishable, and are visible to future starts immediately.

### `questions` and `question_options`

These tables are the mutable authoring pool. Questions are single-choice only and store `options_layout` as `list` or `grid`. Services enforce non-empty question content, at least two usable options, and exactly one correct option. Option codes are stable opaque browser answer values.

Question and option rows are not historical records. Existing attempts remain reproducible because the exact content and order are copied into their snapshots.

### `attempts`

One row owns one student's assessment run:

- `snapshot` is the immutable schema-versioned quiz copy captured at start.
- `answers` is the latest ordered confirmed prefix or the complete final document.
- `answer_version` provides monotonic replacement and idempotency.
- `expires_at` is fixed at start and is the only countdown authority.
- summary columns (`score`, `max_score`, `percent`) support reports without trusting the browser.
- `token_hash` authorizes the attempt without retaining the bearer token.
- unique `(quiz_id, start_key)` makes a successful start retry idempotent.

Status is `in_progress`, `completed`, or `abandoned`. `completed` is terminal for normal completion, intentional exit, and browser-reported expiry. `abandoned/expired` is a lazy provisional server result and may be replaced by a valid delayed Finish, which sets `late_sync = 1`.

There are no `quiz_papers`, `attempt_answers`, `practice_keys`, or `cheat_events` tables.

## Attempt snapshot

Start runs in a transaction while holding the quiz row lock. It first looks up `(quiz_id,start_key)` so a successful retry returns the original attempt even when the live quiz changed afterward. A new start then verifies that the ticket's quiz ID/version still matches a published quiz, validates authoring invariants, captures the snapshot, and inserts the attempt.

The snapshot schema is:

```json
{
  "schemaVersion": 1,
  "quiz": {
    "title": "Quiz title",
    "description": "",
    "shareToken": "123456789",
    "timeLimitSec": 7200,
    "askEmail": false,
    "askPhone": false,
    "feedback": "at_end",
    "showScore": true,
    "showAnswers": false,
    "showExplain": false
  },
  "cover": null,
  "questions": [
    {
      "id": "12",
      "content": "Question text",
      "optionsLayout": "list",
      "media": null,
      "explanation": "",
      "correctCode": "opaque-code",
      "options": [
        {"id": "34", "code": "opaque-code", "content": "Answer", "media": null}
      ]
    }
  ]
}
```

The service samples a random `draw_count` subset. When question shuffling is disabled, that selected subset is returned to authored order. When enabled, the sampled/shuffled order is kept. Options are shuffled independently. The JSON is never rewritten after insertion.

Private media is stored as `{key,type,src}`. `src` is never returned to the browser. Student projections and report views resolve the snapshot reference and generate a signed media URL. Old files are deleted only when absent from live rows and a bounded attempt-snapshot scan can prove it is safe; an incomplete bounded scan fails closed.

## Answer documents and grading

Stored answers are a JSON list:

```json
[
  {"questionId":"12","answerCode":"opaque-code","answeredAt":"2026-10-25T12:00:00.000Z"},
  {"questionId":"13","answerCode":null,"answeredAt":"2026-10-25T12:00:20.000Z"}
]
```

Progress must be an ordered confirmed prefix of the snapshot. A null `answerCode` with a timestamp is an explicit skip. Final documents contain every question; an unreached tail on exit/expiry uses both null values. Normal completion requires a timestamp for every entry.

The server validates exact object keys, question order, option membership, ordered timestamps, start/deadline boundaries, and finish timing. It never reads score or correctness from the client.

`PUT .../progress` accepts `{answerVersion,answers}`. A higher version replaces the saved prefix; the same version is idempotent only for an identical document; a lower version is stale.

`POST .../finish` accepts `{answerVersion,answers,finishReason,clientFinishedAt}`. It locks the attempt, validates the complete document, replaces the checkpoint, calculates equal-weight score/percentage, and writes terminal fields atomically. An identical retry returns the same official result. Terminal attempts reject progress. A valid delayed Finish may replace provisional `abandoned/expired` data.

## Routes and browser state

Public pages:

- `GET /q/{shareToken}` — introduction/admission.
- `GET /q/{shareToken}/start` — quiz player.
- `GET /q/{shareToken}/results` — result player view.

There is no `/play` route. A direct `/start` without matching local state returns to the introduction. The introduction redirects to `/start` when this browser has an active local run.

Player API:

- `GET /api/v1/player/tickets/{shareToken}`
- `POST /api/v1/player/starts`
- `GET /api/v1/player/assessments/{attemptId}`
- `PUT /api/v1/player/assessments/{attemptId}/progress`
- `POST /api/v1/player/assessments/{attemptId}/finish`
- `GET /api/v1/player/assessments/{attemptId}/results`
- `POST /api/v1/player/assessments/{attemptId}/media`

The local format is version 5. It stores the snapshot projection, credential, fixed deadline, confirmed answers, answer/server versions, current draft, and pending progress/final outbox flags. Web Locks prevent two tabs for the same share token from advancing the same local run. Confirmed answers cannot be revisited.

The countdown derives from `expiresAt`, not decrement persistence, so closing/backgrounding cannot reset it. It renders total minutes and seconds (`120:00`). At zero the player freezes input, adds the unanswered tail, shows local results, and queues Finish with reason `expired`.

Once loaded, the run works through connection loss and browser restarts. Progress is silently retried. Finish carries the full document and supersedes pending progress. There is deliberately no service worker, so opening/reopening a page requires connectivity.

## Lifecycle and reporting

Publish is `draft → published`; unpublish is `published → draft`. A published save updates the authoring pool for future starts but does not touch existing attempt snapshots. Soft delete preserves the underlying status and does not revoke an existing attempt bearer credential.

Teacher list/detail reporting reads summary columns plus `snapshot` and `answers`. Detail reconstructs presented question/option order, selected/correct answers, explanation, and media entirely from the attempt. In-progress reports can show only checkpoints already received; unsynchronized browser work is unknowable.

The per-quiz attempt list is always newest first and supports only student search, minimum score, and a start-date preset. Date presets are rolling 24-hour, 7-day, and 30-day periods, the previous calendar day in the teacher's timezone, or all time.

## Security boundaries

- Teacher ownership always comes from `service('auth')->id()`.
- Session-authenticated mutations use CSRF protection.
- Player API requests require the player header/filter and attempt bearer credential where applicable.
- SQL uses models, Query Builder, or bound parameters.
- Views escape authored and identity content as plain text.
- Uploaded media is validated by content and kept private.
- Share tokens and browser-visible answer keys are not examination-security boundaries.

## Development reset

`docs/attempt-snapshot-reset.sql` is the manual destructive phpMyAdmin upgrade for development. It preserves `users`, drops all quiz/student tables, and recreates the canonical four quiz tables. It never runs automatically and does not touch the filesystem.

After a successful reset, private quiz media can be cleared separately:

```bash
find /Applications/MAMP/htdocs/123test-uz/writable/uploads/quiz-media -mindepth 1 -delete
```

Verify the absolute path before running that destructive command. Never import `docs/schema.sql` over a non-empty database.
