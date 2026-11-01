# 123test architecture

## Platform

123test is a server-rendered CodeIgniter 4 application running on PHP 8.4 and MySQL 8.4. It uses plain JavaScript/CSS for the student player and a pinned local Vue 3 production build for the quiz builder. There is no Node/Vite production runtime or SPA router.

`public/` is the web root. Runtime data and quiz media live under `writable/`. Environment values and secrets belong in the untracked root `.env`.

Controllers parse HTTP input and return responses. Services own validation, transactions, scoring, and state changes. Models provide narrow persistence. Explicit routes are used with automatic routing disabled.

The CodeIgniter application timezone is Asia/Tashkent, which supplies calendar boundaries such as Yesterday. Persisted application timestamps remain UTC and are explicitly converted for display and local-calendar filtering.

## Data model

`docs/schema.sql` is the complete fresh-install source of truth. The project does not use migrations.

### `quizzes`

Stores the teacher-owned live definition and its lifecycle. `public_id` is the reporting/builder identifier; the nine-digit `share_token` locates the public quiz. Status is only `draft` or `published`; `deleted_at` provides trash/restore without erasing reports. There is no per-quiz listing flag or optimistic-lock counter: teacher writes are last-write-wins and `updated_at` is informational.

The builder rounds numeric timer input to the nearest whole minute from 1–120 and converts it to the existing `time_limit_sec` runtime value. Missing, malformed, non-finite, or out-of-range timer input silently defaults to 120 minutes instead of rejecting the save. For question selection, numeric input rounds to the nearest question; values from one through the current pool are retained, while missing, malformed, non-finite, zero, negative, or above-pool values normalize to `draw_count = NULL` for the entire pool. Active saves require at least one question and are visible to future starts immediately.

### `questions` and `question_options`

These tables are the mutable authoring pool. Questions are single-choice only and store `options_layout` as `list` or `grid`. The builder immediately mirrors that selection as a one-column or two-column answer editor, with a narrow-screen single-column fallback, while the same stored value controls the student player. Question text may be blank. On Save, services pad missing choices to two, replace blank choice text with `Option N`, and enforce exactly one correct option by retaining the first marked choice or selecting the first option. Option codes are stable opaque browser answer values.

Question media is restricted to private images or audio; answer options and covers are image-only. The media API accepts multipart file uploads and does not accept video URLs. Video descriptors from old attempt snapshots are ignored rather than rendered, while the snapshots themselves remain immutable.

Question and option rows are not historical records. Existing attempts remain reproducible because the exact content and order are copied into their snapshots.

### `attempts`

One row owns one student's assessment run:

- `snapshot` is the immutable schema-versioned quiz copy captured at start.
- `answers` is the latest ordered confirmed prefix or the complete final document.
- `answer_version` provides monotonic replacement and idempotency.
- `expires_at` is fixed at start and is the only countdown authority.
- summary columns (`score`, `max_score`, `percent`) support reports without trusting the browser.
- the random unique `public_id` is the reporting locator, the per-quiz cookie value, and the pre-issued idempotency key used by Start.

Status is `in_progress`, `completed`, or `abandoned`. `completed` is terminal for normal completion, intentional exit, and browser-reported expiry. `abandoned/expired` is a lazy provisional server result and may be replaced by a valid delayed Finish, which sets `late_sync = 1`.

There are no `quiz_papers`, `attempt_answers`, `practice_keys`, or `cheat_events` tables.

## Attempt snapshot

Start runs in a transaction while holding the quiz row lock. A 15-minute admission ticket binds the quiz ID and a server-generated attempt `public_id`. The service first looks up that public ID, so a successful retry returns the original attempt even when the live quiz changed afterward. A new start verifies that the current quiz is still published and not deleted, validates its latest authoring state, captures that definition, and inserts the attempt using the ticket's ID.

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

Private image/audio media is stored as `{key,type,src}`. `src` is never returned to the browser. Student projections and report views resolve supported snapshot references and generate signed media URLs. Old files are deleted only when absent from live rows and a bounded attempt-snapshot scan can prove it is safe; an incomplete bounded scan fails closed.

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

Teacher authoring uses `PUT /api/v1/quizzes/{publicId}` as the only content/status save endpoint. Its document includes storage status `draft|published` and `timeLimitMinutes`; both browser and service normalize the timer, and the service converts it to seconds before committing content, status, publication timestamp, and a refreshed `updated_at` in one transaction. Every explicit **Save quiz** or **Saved** click performs this write. The builder has no autosave, Publish/Unpublish endpoints, version field, conflict/overwrite flow, or public-listing checkbox. Concurrent teacher writes are last-write-wins. Media actions save pending edits only when server IDs are needed; media requests carry no expected version and return refreshed `updatedAt`. Pending-cover handling, in-flight edit merging, retry states, and unsaved-navigation protection remain in force.

Attempt API requests use one cookie named `edutest_attempt_{shareToken}`. Its raw `public_id` value is HttpOnly, SameSite=Strict, scoped to `/`, Secure on HTTPS, and expires 24 hours after the attempt deadline. The server independently enforces the same grace boundary. Different quiz cookies can coexist; starting another run of the same quiz replaces that quiz's cookie. Assessment URLs keep their public ID and require it to match the cookie selected from the attempt's owning quiz.

Public pages resolve that cookie before rendering. The introduction redirects only an unexpired in-progress run to Start. Start redirects missing sessions to the introduction and terminal/expired sessions to Results; Results performs the inverse active-run redirect. The server injects the resolved attempt ID into the player shell because JavaScript cannot read an HttpOnly cookie.

The local format is version 6. It stores the attempt ID, snapshot projection, fixed deadline, confirmed answers, answer/server versions, current draft, and pending progress/final outbox flags, but no authorization credential. Web Locks prevent two tabs for the same share token from advancing the same local run. A missing local document is reconstructed from the cookie-owned server checkpoint. When localStorage names another attempt, the cookie wins and the old document moves to a recovery key that is never submitted under the new session. Format-5 bearer state is preserved only as unsynchronized recovery data. Confirmed answers cannot be revisited. Submit may show configured feedback; Skip records an explicit unanswered entry and advances immediately without feedback.

The countdown derives from `expiresAt`, not decrement persistence, so closing/backgrounding cannot reset it. It renders total minutes and seconds (`120:00`). At zero the player freezes input, adds the unanswered tail, shows local results, and queues Finish with reason `expired`.

Once loaded, the run works through connection loss and browser restarts. Progress is silently retried. Finish carries the full document and supersedes pending progress. There is deliberately no service worker, so opening/reopening a page requires connectivity.

The active player keeps the quiz title in the shared header and places `Question N of M` above the confirmed-answer progress bar. Correct feedback avoids repeating the selected and correct choice; incorrect or unanswered review feedback reveals the correct choice only when the captured visibility settings allow it. Result percentages retain full precision in data and scoring but display as truncated whole numbers. Review verdicts use distinct correct, wrong, and unanswered badges.

## Lifecycle and reporting

The teacher-facing lifecycle is Draft/Active while storage remains `draft|published`. Saving Draft→Active sets `published_at`; later Active saves preserve it, and saving Active→Draft also preserves it. The same manual Save updates the authoring pool for future starts but does not touch existing attempt snapshots. Soft delete preserves the underlying status and does not revoke an existing attempt cookie.

Teacher list/detail reporting reads summary columns plus `snapshot` and `answers`. Detail reconstructs presented question/option order, selected/correct answers, explanation, and media entirely from the attempt. In-progress reports can show only checkpoints already received; unsynchronized browser work is unknowable.

The dashboard lists only non-deleted quizzes, ordered by most recently updated, with pagination but no status or sort filters. Soft-deleted quizzes remain absent from the dashboard while their historical reports remain owner-accessible.

The per-quiz attempt list contains only attempts with an authoritative `finished_at`, ordered by newest completion first. It supports student search, minimum score, and a completion-date preset. Date presets use `finished_at`: exact rolling 24-hour, 7-day, and 30-day periods; the previous complete calendar day in Asia/Tashkent converted to UTC boundaries; or all completed attempts.

## Security boundaries

- Successful teacher login or registration creates the existing HttpOnly, SameSite=Lax CodeIgniter session with a rolling one-year lifetime. The framework renews its persistent cookie during session-ID regeneration, retains matching server-side session files for the same configured lifetime, and explicit Logout destroys the authenticated session immediately. Production deployments require HTTPS and secure-cookie configuration.
- Teacher ownership always comes from `service('auth')->id()`.
- Session-authenticated mutations use CSRF protection.
- Player API requests require the player header/filter and the matching per-quiz attempt cookie. SameSite=Strict, same-origin/Origin and Fetch Metadata checks, plus the non-form custom header protect cookie-authenticated mutations while player APIs remain outside teacher-session CSRF.
- SQL uses models, Query Builder, or bound parameters.
- Views escape authored and identity content as plain text.
- Uploaded media is validated by content and kept private.
- Share tokens and browser-visible answer keys are not examination-security boundaries.
- The raw attempt public ID is intentionally an MVP-strength cookie credential. Anyone who learns it and manually reproduces the cookie can act as that attempt until the server-side grace window closes.

## Development reset

`docs/attempt-snapshot-reset.sql` is the manual destructive phpMyAdmin upgrade for development. It preserves `users`, drops all quiz/student tables, and recreates the canonical four quiz tables. It never runs automatically and does not touch the filesystem.

After a successful reset, private quiz media can be cleared separately:

```bash
find /Applications/MAMP/htdocs/123test-uz/writable/uploads/quiz-media -mindepth 1 -delete
```

Verify the absolute path before running that destructive command. Never import `docs/schema.sql` over a non-empty database.
