# EduTest architecture

Status: evolving implementation guide. Keep this document concise and update it when the implemented frontend or backend design changes. Product intent belongs in [PLAN.MD](PLAN.MD); exact database structure belongs only in [schema.sql](schema.sql).

## System overview

EduTest is one CodeIgniter 4 application with two experiences:

- Teachers authenticate, create quizzes, publish/share them, and review assessment results.
- Students use a public quiz link for a saved assessment or anonymous practice session.

The application uses PHP 8.4, project-owned CI4 session authentication, MySQL/InnoDB, server-rendered views, and private filesystem storage. It does not require a runtime Node service, SPA router, queue, WebSocket server, or microservice.

## Backend

Use this separation:

```text
app/
  Controllers/{Teacher,Student,PublicSite}/
  Filters/
  Services/                         # Existing authoring, media, auth, and query services
  Services/Player/                  # Admission, assessment, practice, definitions, scoring, credentials
  Models/
  Views/{layouts,teacher,student,public}/
  Commands/
```

- Controllers parse requests, enforce the authenticated entry point, call one application service, and format a response.
- Services own transactions, authorization, quiz lifecycle, scoring, retry safety, and coordination across models.
- Domain classes hold definitions, validation rules, policies, and state types without HTTP concerns.
- Models handle persistence and never accept ownership from request data.
- Filters apply session authentication, CSRF, bearer-token checks, and throttling.
- Cleanup must use bounded batches; assessment deadlines do not automatically finalize possibly unsynchronized work.

Use explicit routes with automatic routing disabled. Register fixed routes before the final public `/{username}` route.

## Frontend

Server-rendered PHP is the default. Pages share accessible layouts, navigation, flash messages, form errors, and responsive CSS.

- Teacher dashboard, quiz library, and assessment reports use server-rendered views with small plain-JavaScript enhancements.
- The quiz builder uses the pinned local Vue 3.5.43 global production build without single-file components, Vite, or an SPA router. Its application script URL includes the file modification version so an updated template cannot execute against stale cached builder code.
- Student assessment and practice pages use plain JavaScript modules and server APIs.
- Render authored content as escaped plain text. The introduction contains no answer keys; a successful start deliberately preloads keys for both feedback modes. Private media paths are never sent.
- Support keyboard use, visible focus, responsive layouts, clear save/retry states, and screen-reader-friendly status updates.

The teacher workspace keeps its light-theme design system in `public/assets/css/teacher.css`: tokens, reset, accessibility utilities, and reusable components. Student introduction, player, and results pages load only `public/assets/css/player.css`, which contains their own light-theme tokens, reset, accessibility utilities, components, and responsive layouts; it does not load or import teacher styles. Landing, authentication, and framework error pages retain independent styling. Student state/scoring/synchronization are plain JavaScript modules; no frontend build or runtime Node service is required.

Browser state is limited by mode:

- Assessments store the bearer credential, content, and pending answer outbox in same-tab `sessionStorage`. The server controls persisted locks and official scoring, not offline timer compliance.
- Practice stores its progress and review data in the current tab only; clearing tab storage loses the session.

### Implemented teacher authoring

Successful login and registration lead to `/quizzes`; `/dashboard` remains the overview. Authenticated teacher pages are server-rendered at `/dashboard`, `/quizzes`, `/quizzes/archived`, `/quizzes/trash`, `/quizzes/{publicId}/edit`, and the `/results` reporting routes. They share a responsive teacher layout with overview, library, and Results navigation, profile context, and a CSRF-protected logout form.

The dashboard queries quiz totals, published quizzes, finalized assessment submissions, practice starts, and recently changed quizzes. The library supports title search, status/mode filters, sorting, pagination, and separate active, archived, and trash views. Empty states and metrics always come from stored data.

The creation dialog accepts an optional image cover with local preview. It creates the draft, loads its version, and uploads through the existing private-cover API using serialized CSRF-aware mutations before opening the builder. Upload failure retains the draft identifier for retry or continuation without a cover; cancellation does not delete a saved draft. Cancel and × submit a separate native `method="dialog"` form without validation, independent of creation/close handlers. Close-event cleanup restores opener focus and clears temporary preview state. Requests temporarily disable editing and dismissal. The teacher script URL includes its file modification version to refresh browser caches after updates.

The builder uses the locally vendored Vue 3.5.43 global production build and same-origin JSON endpoints under `/api/v1`. One “Add question” action creates a two-choice single-choice question; teachers can change it immediately to multi-select or short text. Choice correctness uses radio controls for single choice and checkboxes for multi-select, and choices are lettered `A)`, `B)`, … in their displayed order. Questions have equal weight and no individual timer. The optional total timer is authored as `timeLimitMinutes` in half-minute steps and converted to whole seconds for storage/runtime. Answer options accept image media only; question media retains image, audio, and validated video. Teacher preview renders the current document without grading, starting an attempt, or exposing it publicly.

The builder saves one versioned quiz document containing settings, questions, options, accepted text answers, and safe media descriptors. Changes autosave one second after the last edit and may also be flushed explicitly; invalid documents enter a visible validation-error state instead of being persisted. Mutations are serialized so regenerated CSRF tokens remain ordered, and navigation warns while changes are pending. The service locks the owned quiz, compares its version, validates the complete aggregate and nested ownership, and writes quiz, question, and option changes in one transaction. A version mismatch returns `409` and pauses saving until the teacher reloads the server document or explicitly overwrites it.

Passcodes use explicit `unchanged`, `set`, and `clear` operations. Only the configured/not-configured state is returned to the client; a passcode value or hash is never returned. IDs, share tokens, and option codes are generated on the server.

Lifecycle changes are service-owned: drafts publish only after full content validation; published quizzes close and closed quizzes reopen; any active quiz may be archived. Unarchiving returns a never-started quiz to draft and a previously started quiz to closed. Published quizzes must first be closed or archived before soft deletion, and restoration preserves the stored lifecycle status. Duplication creates an unstarted, unlisted draft with new identifiers, zero activity, and independent media copies.

The student-start transaction creates or reuses an immutable `quiz_papers` JSON definition for the current quiz revision and binds the assessment attempt or practice retry key to it. `first_started_at` records historical first use and locks only assessment/practice mode. All other authored content and settings remain editable; a successful start always receives one complete old or new revision under the quiz-row lock. Builder saves that affect a starting definition increment `quizzes.revision`, while lifecycle/listing-only changes do not. Published edits must remain publication-ready or the transaction rolls back.

`quiz_papers` is unique by `(quiz_id, revision)` and stores a server-owned schema-versioned JSON definition without passcode hashes, credentials, identity, responses, scores, expiring URLs, question weights, or per-question timers. Paper schema version 2 uses equal-weight questions. `attempts.paper_id` and `practice_keys.paper_id` use a same-quiz composite foreign key. `attempt_items.question_id` is a historical identifier without a foreign key to mutable `questions`, allowing live rows to be removed safely. Paper media stores private references only inside server-owned JSON; client projections contain signed descriptors.

Published and closed quizzes have a stable `/q/{shareToken}` introduction with safe metadata, optional cover, instructions, admission fields, and availability. Draft, archived, deleted, and unknown introduction tokens return 404. `/q/{shareToken}/play` and `/q/{shareToken}/results` are credential-free shells; actual attempt data requires the tab's bearer. These shells do not gate an existing attempt on current quiz availability.

### Implemented teacher reporting

The read-only Results workspace is server-rendered at `/results`. It reports assessment quizzes only and includes active, archived, and soft-deleted quizzes so lifecycle changes do not hide historical attempts. Overview queries provide finalized/in-progress totals, finalized average scores, per-quiz summaries, safe search/sort filters, and 20-row pagination.

`/results/quizzes/{quizPublicId}` provides the basic finalized count, in-progress count, finalized average score, and a 25-row attempt list. The list may filter finalized, submitted, expired, in-progress, or all saved attempts; teacher-local date boundaries are converted to UTC before querying. Distribution charts and question-level aggregate analytics are intentionally omitted. Integrity events remain informational per-attempt observations.

`/results/attempts/{attemptPublicId}` reviews the complete saved attempt in its captured question and choice order, including drafts for an in-progress attempt. The bound immutable paper is authoritative even when live questions are edited or deleted. The page shows lettered choices, submitted answers, answer keys, accepted text, Correct/Partial/Wrong/Unanswered status, response timing, explanations, signed media, captured policy fields, and a chronological integrity timeline. IP address and browser user agent appear only on this owner-authorized detail page. Event metadata is escaped and credential-like keys are removed.

`/results/quizzes/{quizPublicId}/export.csv` applies the same attempt filters without UI pagination and reads database rows in bounded batches. The UTF-8 CSV contains public attempt identity, lifecycle/result fields, timezone-offset timestamps, duration, and integrity-event counts. Spreadsheet-leading formula characters are neutralized. IP addresses, browser agents, answers, event metadata, database IDs, credentials, hashes, passcodes, and private media paths are excluded.

`Teacher\ResultsController` obtains ownership only from `service('auth')->id()`. `TeacherResultsService` owns filter normalization, owner-scoped queries, basic aggregates, timezone conversion, safe attempt projections, and export iteration. Malformed, missing, practice-mode, and non-owned quiz or attempt identifiers all resolve to the same 404 boundary. Reporting responses are private and non-cacheable.

## Identity and authorization

EduTest owns email/password registration, login, logout, and credential storage. Persistent account, profile, password-hash, and reserved recovery fields live in the single `users` table. The session stores only the authenticated user ID; CI4 file/cache storage holds temporary sessions, CSRF state, and rate-limit buckets.

Passwords use PHP's `PASSWORD_DEFAULT` API and are rehashed when needed. Registration starts a session immediately. Email verification and password recovery are deferred; recovery database fields are reserved but have no routes or UI. Every account is a teacher in the MVP, so there are no role, group, identity, or permission tables.

Teachers directly own quizzes. Authorize nested resources through the complete ownership chain. Public IDs, share tokens, question IDs, option codes, attempt tokens, and signed media URLs are identifiers or credentials, not proof of teacher ownership.

Teacher mutations use session authentication and CSRF protection. A signed 15-minute admission ticket binds quiz version, settings, shuffle seed, and random start identifiers. Assessment bearers are pseudorandom HMAC credentials derived from those identifiers, persisted only as SHA-256 hashes. The browser stores the recovery credential before POST so a lost start response remains recoverable. Practice playback tickets expire after 24 hours and are renewable while valid, without creating an account or result record.

Student requests never use teacher cookies for authorization. A same-origin/custom-header filter, JSON content type, bounded request bodies, and endpoint-level throttling protect the stateless namespace. Session CSRF is exempt only for public student/media paths; teacher and authentication routes retain it. Private wrappers exclude student/media traffic from CI4 page caching and debug-toolbar persistence, including development mode.

## Quiz lifecycle

Quizzes move through draft, published, closed, and archived states and may be soft-deleted. Drafts do not accept students.

Each real start binds the run to an immutable paper for the current `quizzes.revision`. Teachers may edit, delete, or reorder live content afterward; future starts use a new revision while existing assessments, results, and renewable practice credentials continue from their bound paper. Mode alone becomes immutable after `first_started_at` is set.

## Assessment flow

An assessment start validates availability, optional passcode, identity fields, and quiz definition in one transaction. Each deliberate start creates an independent attempt; retrying the same start request returns the existing attempt.

Admission locks the quiz, validates readiness/identity/passcode, creates or reuses the current immutable paper, and creates attempt items with persisted question/option order. Sync locks the attempt and accepts ordered batches of up to 100 changed items. It validates membership and progression, uses per-item save versions and immutable submission key/hash pairs, checks the aggregate version, and increments once per changed transaction. Acknowledgement replays do not increment. Any invalid item rolls the whole batch back. Scores are calculated from the bound paper answer keys, never submitted scores.

The browser advances immediately and drains a serialized outbox in the background. Newer local drafts survive acknowledgements and transient failures. Conflicting immutable submissions pause synchronization and require an explicit choice before replacing local work. Students see provisional results until server confirmation; numeric scores and answer/explanation display follow captured visibility policy. Student database transactions use quiz-first locking, rollback checks, and at most three attempts for MySQL deadlocks/lock timeouts; other errors are not retried.

The optional total timer and captured closing deadline continue through feedback and backgrounding; the earliest deadline wins. There are no per-question deadlines or `question_timeout` submissions. Start/receipt times are server-generated, while offline timer compliance remains client-enforced and unverified. Work arriving after recorded deadlines is accepted with `late_sync`. Abandoned attempts remain incomplete rather than being automatically closed before offline work can arrive. Closed/archived/soft-deleted quizzes still permit existing authorized attempts to finish using their starting policy snapshot.

Opt-in integrity events record visibility/focus, fullscreen exits, and 60-second inactivity. They are idempotent informational signals, never automatic penalties. The tab queue is bounded to 1,000 events; practice sends none.

## Practice flow

Practice is anonymous and non-authoritative. It persists no identity, IP address, browser agent, answer, score, or individual result in the database, server session, cache, or application logs.

Start returns the entire bound paper definition and answer keys for local grading. Practice never posts answers or results. `practice_keys` stores quiz/paper/key/expiry for 24-hour deduplication; expired rows are cleaned in bounded batches during starts. Retry reconstruction uses signed starting settings and seed. Teachers see the aggregate count, not unique people or completions. Throttling stores endpoint counters, not practice identities.

Completed practice results offer “Try again” through a fresh admission/start request, not a local-only reset. New runs honor current availability/settings and configured shuffling, restart timers/progress, and increment the start count. The previous result remains until success. A pending retry ticket is bound to the completed run in tab state so failed/lost responses reuse its deduplication key; successful restarts replace tab state and clear timer warnings. Media renewal responses for an older run cannot overwrite the new run's credentials.

## Scoring

Use the PHP scoring engine for official assessment results and the JavaScript counterpart for instant feedback/practice, with shared fixtures. Each question has fixed maximum credit `1.00`:

- Single choice and short text: `1.00` or `0.00`.
- Multi-select: proportional right-minus-wrong credit, clamped from `0.00` to `1.00`.

Use exact decimal arithmetic and round each multi-select credit half-up to two decimal places before totaling. `attempt_items.credit` is internal per-question credit; attempt `score` is the equivalent correct-question count, `max_score` is the question count, and `percent` remains the percentage. User interfaces describe results as questions, not points.

## Media

Questions may reference one image, audio file, or supported external video; answer options and quiz covers are image-only. Uploaded files remain outside `public/` under random paths; decoded images are limited to 16 megapixels before processing.

Validate file size, decoded content/MIME, and ownership. Re-encode images where required, reject unsafe formats, and serve private files only through short-lived signed URLs. A stored private path must never appear in a client response.

The authoring implementation stores random quiz media below `writable/uploads/quiz-media`. JPEG, PNG, and WebP images up to 5 MiB are decoded and re-encoded to strip metadata; supported question audio up to 20 MiB is content-validated and stored with a server-selected extension. YouTube, Vimeo, and direct HTTPS MP4 question URLs are normalized rather than accepted as arbitrary embed markup. Answer-option endpoints reject audio and video. Media mutations require a persisted owned target, follow the complete ownership chain, compare the current quiz version, and increment it transactionally.

Failed media writes remove newly created files. Replaced or deleted files are cleaned after the database commit, while soft-deleted quiz media is retained for restoration. Duplicated quizzes receive physically independent copies of uploaded media.

Private images and audio are delivered through expiring HMAC-signed `/media/{token}` URLs with range support and `nosniff`/private-cache headers. Signing uses the untracked `encryption.key` environment value; database responses contain a temporary playback URL, never `media_src`.

Cover mutations use the same ownership/version/cleanup rules and physically independent duplication. Media changes and starts serialize under the quiz lock. Paper descriptors use an opaque media key and paper public ID; signed delivery resolves the current paper record without exposing its stored path. Replaced files are deleted only when neither live rows nor retained papers reference them. Assessment papers remain while attempts reference them; expired practice-only papers are eligible for bounded cleanup. The browser keeps a bounded in-memory image cache; unloaded audio/video and reloads are not guaranteed offline.

## API and errors

The authoring API provides CSRF recovery; quiz create/load/save; lifecycle and duplication; and cover/question/option media replacement and removal. All routes require the teacher session and mutations remain CSRF-protected.

The separate `/api/v1/player` API provides admission tickets, starts, bearer-authenticated assessment loading/sync/results/integrity/media, and stateless practice media renewal. Its envelopes use timestamp metadata only, with no session or CSRF token generation. See [STUDENT_PLAYER.md](STUDENT_PLAYER.md) for endpoints, schema expectations, and verification.

Responses use `{data, meta}` and `{error, meta}` envelopes with camelCase fields. Timestamps are UTC ISO strings, decimals are exact strings, and numeric database IDs are serialized as strings for JavaScript safety. Authoring services derive ownership only from the authenticated session and ignore submitted ownership identifiers.

Return appropriate 4xx errors for validation, authentication, authorization, missing resources, expired credentials, and version conflicts. Do not expose stack traces, SQL, secrets, or internal filesystem paths.

## Operations

Local development uses MAMP and phpMyAdmin. Production targets cPanel/shared hosting with TLS, SMTP, cron, backups, and `public/` as the preferred document root.

Keep secrets in `.env`, writable data private, production errors hidden, and ordinary logs free of credentials, tokens, signed URLs, and practice bodies. Back up the database and media before applying SQL changes and test restore procedures before launch.

Authoring deployments require an untracked `encryption.key`. To support the documented 20 MiB audio limit, the PHP web runtime must set `upload_max_filesize` above 20 MiB and `post_max_size` above that value; the builder reports a configuration warning when the effective runtime limits are lower.
