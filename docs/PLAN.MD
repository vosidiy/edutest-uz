# 123test product plan

## Product direction

123test is a simple teacher-owned assessment product. The MVP supports one quiz mode and one question type: Assessment quizzes made from single-choice questions with exactly one correct option.

The teacher creates a quiz, optionally limits the random draw, chooses question/option shuffling, publishes one stable nine-digit share link, and reviews individual results. Published edits are live for the next student start. They never change an attempt that has already started.

## MVP defaults and limits

- Name is required at admission.
- Email and phone are optional fields and are shown only when enabled by the teacher.
- Every quiz has a timer. The default and maximum are 7,200 seconds; valid values are 30–7,200 seconds in 30-second steps.
- Every question has at least two options and exactly one correct option.
- Question layout is `list` or `grid`.
- `draw_count = NULL` serves the whole pool. A positive draw count randomly samples that many questions for each attempt.
- Question and option shuffling are independent.
- Feedback is `at_end` or `after_each`.
- Explanations can be shown only when correct answers are also shown.
- Answer keys are intentionally present in the loaded browser document. This is not a high-security examination system.

Practice mode, text answers, multiple selection, schedules, passcodes, integrity monitoring, archived/closed lifecycle states, and per-question timers are outside this MVP.

## Student flow

1. `GET /q/{shareToken}` shows the introduction and admission form.
2. A browser with an active local attempt is redirected to `/q/{shareToken}/start`.
3. Start locks the live quiz row, validates the current published definition, draws/shuffles questions and options, and inserts one immutable snapshot in `attempts.snapshot`.
4. The player stores the loaded run locally. It can continue without a network connection after loading and survives a browser restart through `localStorage`.
5. Submit and Skip append to an ordered confirmed answer prefix and silently checkpoint the whole prefix.
6. Finish sends the complete answer document. The server locks the attempt, validates it against the stored snapshot, replaces the checkpoint, and calculates the official result.
7. At the fixed `expires_at`, the browser freezes input, fills the unanswered tail as skipped, renders immediately, and queues an `expired` Finish.
8. Intentional Exit does the same with reason `exited`.

No service worker is planned. Reopening the page still needs a connection even though a page that is already loaded can continue offline.

## Attempt ownership

Each Assessment attempt is the equivalent of one printed paper. Its `snapshot` contains the exact presented content/order, visibility settings, answer keys, explanations, and stable media references. Its `answers` contains the current confirmed prefix or complete final document.

`quiz_papers`, `attempt_answers`, `practice_keys`, and `cheat_events` are not part of the product. Abandoned work is unimportant except that the latest checkpoint is lazily graded after expiry for teacher visibility. A valid delayed final upload can replace that provisional result.

## Teacher flow

- Lifecycle is `draft → published`, with `published → draft` unpublish.
- A published save must remain fully publishable and affects only future starts.
- Soft deletion keeps bearer-authorized attempts and historical reports available.
- Reports decode each attempt's snapshot and answers, showing correct, wrong, and skipped questions.
- The builder exposes draw count, list/grid layout, timer, shuffle, contact, feedback, and result-visibility settings only.

## Privacy and security

- Teacher mutations use session authentication and CSRF protection.
- Attempt API access uses a random bearer credential stored only as a SHA-256 hash on the server.
- Nine-digit share tokens are convenient locators, not secrets; player routes remain throttled.
- Private media stays outside `public/`; attempt projections replace stored private paths with short-lived signed URLs.
- The application never accepts client-provided scores or correctness.

## Delivery milestones

1. Canonical schema and destructive development reset.
2. Single-choice builder and publish/unpublish lifecycle.
3. Attempt snapshot creation and idempotent admission.
4. Offline-capable player with versioned progress and final synchronization.
5. Snapshot-backed reports and media renewal.
6. Focused PHP/JavaScript/browser behavior verification and deployment documentation.

Future improvements may add better offline installation, richer analytics, accessibility refinements, and additional question types only after the reduced MVP is stable.
