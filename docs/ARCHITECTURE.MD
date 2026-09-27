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

- The unified teacher dashboard and quiz-scoped assessment reports use server-rendered views with small plain-JavaScript enhancements.
- The quiz builder uses the pinned local Vue 3.5.43 global production build without single-file components, Vite, or an SPA router. Its application script URL includes the file modification version so an updated template cannot execute against stale cached builder code.
- Student assessment and practice pages use plain JavaScript modules and server APIs.
- Render authored content as escaped plain text. The introduction contains no answer keys; a successful start deliberately preloads keys for both feedback modes. Private media paths are never sent.
- Support keyboard use, visible focus, responsive layouts, clear save/retry states, and screen-reader-friendly status updates.

The teacher workspace keeps its light-theme design system in `public/assets/css/teacher.css`: tokens, reset, accessibility utilities, and reusable components. Student introduction, player, and results pages load only `public/assets/css/player.css`, which contains their own light-theme tokens, reset, accessibility utilities, components, and responsive layouts; it does not load or import teacher styles. Landing, authentication, and framework error pages retain independent styling. Student state/scoring/synchronization are plain JavaScript modules; no frontend build or runtime Node service is required.

Browser state is limited by mode:

- Assessments store the bearer credential, content, and pending answer outbox in same-tab `sessionStorage`. The server controls persisted locks and official scoring, not offline timer compliance.
- Practice stores its progress and review data in the current tab only; clearing tab storage loses the session.

### Implemented teacher authoring

Successful login and registration lead to `/dashboard`, the single teacher home. Its centered, approximately 1280px-wide layout uses a top header with the EduTest dashboard link and a keyboard-accessible account disclosure containing CSRF-protected logout. There is no sidebar or mobile bottom navigation. Authenticated compatibility routes `/quizzes`, `/quizzes/archived`, and `/quizzes/trash` redirect to the corresponding dashboard view; `/results` redirects to All with assessment-report eligibility enabled. Only recognized search, mode/status, lifecycle, sort, and page parameters are translated.

The dashboard combines active/published quiz counts, finalized/in-progress assessment totals, average finalized percentage, and Practice starts with one enriched quiz library. Assessment metrics include archived/deleted history; quiz/Practice counters retain their existing non-deleted scope. The list supports title search, status/mode/report-eligibility filters, safe sorting, 20-row pagination, and All/Active/Archived/Trash views (Active by default). Each row shows activity, average score, latest submission, current lifecycle/mode, and eligible Builder/Responses links with lifecycle actions. Separately grouped question/attempt summaries avoid multiplying counts or reading response JSON and papers; displayed dates use the teacher timezone. Metrics are workspace-wide and independent of list filters. Empty averages are shown as unavailable, not zero. Dashboard responses are private and non-cacheable.

The creation dialog accepts an optional image cover with local preview. It creates the draft, loads its version, and uploads through the existing private-cover API using serialized CSRF-aware mutations before opening the builder. Upload failure retains the draft identifier for retry or continuation without a cover; cancellation does not delete a saved draft. Cancel and × submit a separate native `method="dialog"` form without validation, independent of creation/close handlers. Close-event cleanup restores opener focus and clears temporary preview state. Requests temporarily disable editing and dismissal. The teacher script URL includes its file modification version to refresh browser caches after updates.

The builder uses the locally vendored Vue 3.5.43 global production build and same-origin JSON endpoints under `/api/v1`. Builder, per-quiz Responses, and attempt detail share a sticky `builder-bar` partial in a quiz workspace without the dashboard brand/profile header. Builder actions and Vue bindings remain builder-only; Responses keeps CSV export and attempt detail keeps Back to responses. Archived/deleted report headers disable Builder and link to the appropriate dashboard restoration view without restricting historical review. Desktop navigation is centered; tablet/mobile navigation wraps below the title, and essential Back/Save actions remain reachable. The shared header provides direct Builder/Responses navigation for current Assessment quizzes and for Practice quizzes that retain historical assessment attempts; Practice quizzes without individual records omit that navigation. Availability and the owner-protected Results URL are read-only fields derived from the last saved server document, so unsaved mode edits cannot create an invalid report link. One “Add question” action creates a two-choice single-choice question; teachers can change it immediately to multi-select or short text. Choice correctness uses radio controls for single choice and checkboxes for multi-select, and choices are lettered `A)`, `B)`, … in their displayed order. Questions have equal weight and no individual timer. The optional total timer is authored as `timeLimitMinutes` in half-minute steps and converted to whole seconds for storage/runtime. Answer options accept image media only; question media retains image, audio, and validated video. Teacher preview renders the current document without grading, starting an attempt, or exposing it publicly.

The builder alone fills the dynamic viewport (with a `100vh` fallback) through a flex/grid height chain, with a naturally sized persistent header. Above 1180px, the question list, editor, and settings content scroll independently; rail headings and Add question remain fixed. At narrower widths, labelled Questions/Editor/Settings controls show one panel without unmounting it or losing its scroll position. Question selection/addition reveals the editor at its top. Each scrolling region is keyboard-focusable and labelled. Existing-attempt, upload-limit, readiness, and error notices live inside the editor; new errors reveal that panel. A collapsed “About answers & timers” disclosure retains the answer-key/offline-timing explanation at its bottom. Responses and dashboard layouts are unaffected.

The header title opens a native Quiz details dialog containing title and cover controls, removed from Settings. Each opening edits a temporary copy; Cancel, ×, and Escape discard only that opening's edits and restore title-button focus. Apply validates the title and stages an optional image upload/removal locally. Preview object URLs remain alive while used by the dialog or pending cover and are revoked when no longer needed. File type, size, and decoded dimensions are checked in the browser; the existing server media validation remains authoritative.

The save button is neutral/disabled when Saved, primary when changes remain, disabled during Saving, and reopens the existing dialog for Resolve conflict. One save pipeline first acknowledges the aggregate and then persists its staged cover using the returned version through the serialized CSRF helper. These are separate transactions: a failed cover keeps its local preview/operation for retry without resaving an unchanged aggregate. Saved and the navigation warning include both content and pending media. A three-way acknowledgement merge preserves newer text/settings, additions, deletions, and reordered row identities made during requests. Reload conflict discards staged work only after a successful load; explicit overwrite retains it. In-flight lifecycle responses also preserve newer local edits.

The builder saves one versioned quiz document containing settings, questions, options, accepted text answers, and safe media descriptors. Draft changes autosave one second after the last edit; published, closed, and archived quizzes require an explicit save so a temporary incomplete edit is not submitted automatically. The draft Publish action is disabled until the local document meets publication-readiness rules, while server validation remains authoritative. Invalid manual saves enter a visible validation-error state instead of being persisted. Mutations are serialized so regenerated CSRF tokens remain ordered, and navigation warns while changes are pending. The service locks the owned quiz, compares its version, validates the complete aggregate and nested ownership, and writes quiz, question, and option changes in one transaction. A version mismatch returns `409` and pauses saving until the teacher reloads the server document or explicitly overwrites it.

Passcodes use explicit `unchanged`, `set`, and `clear` operations. Only the configured/not-configured state is returned to the client; a passcode value or hash is never returned. IDs, share tokens, and option codes are generated on the server. Newly created and duplicated quizzes receive collision-retried nine-digit share codes; legacy 64-character hexadecimal links remain accepted. Share codes locate public introductions and are not authorization secrets.

Lifecycle changes are service-owned: drafts publish only after full content validation; published quizzes close and closed quizzes reopen; any active quiz may be archived. Unarchiving returns a never-started quiz to draft and a previously started quiz to closed. Published quizzes must first be closed or archived before soft deletion, and restoration preserves the stored lifecycle status. Duplication creates an unstarted, unlisted draft with new identifiers, zero activity, and independent media copies.

The student-start transaction creates or reuses an immutable `quiz_papers` JSON definition for the current quiz revision and binds the assessment attempt or practice retry key to it. `first_started_at` records historical first use but does not lock authoring. Content, settings, and mode remain editable; saved mode changes govern future starts while existing runs keep their captured mode. A successful start always receives one complete old or new revision under the quiz-row lock. Builder saves that affect a starting definition, including mode, increment `quizzes.revision`, while lifecycle/listing-only changes do not. Published edits must remain publication-ready or the transaction rolls back.

`quiz_papers` is unique by `(quiz_id, revision)` and stores a server-owned schema-versioned JSON definition without passcode hashes, credentials, identity, responses, scores, expiring URLs, question weights, or per-question timers. Paper schema version 2 uses equal-weight questions. `attempts.paper_id` and `practice_keys.paper_id` use a same-quiz composite foreign key. Historical question IDs and exact presented order live in `attempts.responses`, with no dependency on mutable question rows. Paper media stores private references only inside server-owned JSON; client projections contain signed descriptors.

Published and closed quizzes have a stable `/q/{shareToken}` introduction with safe metadata, optional cover, instructions, admission fields, and availability. The route accepts current nine-digit codes and preserved 64-character hexadecimal links. Draft, archived, deleted, malformed, and unknown introduction tokens return 404. `/q/{shareToken}/play` and `/q/{shareToken}/results` are credential-free shells; actual attempt data requires the tab's bearer. These shells do not gate an existing attempt on current quiz availability.

### Implemented teacher reporting

The reporting overview is integrated into `/dashboard`; `/results` is a compatibility redirect rather than a separate screen. The report-eligible filter includes every current Assessment quiz and any owned quiz with historical assessment attempts, even when its current mode is Practice or it is archived/soft-deleted. Workspace totals and per-quiz summaries share the dashboard library rather than duplicating it in a second results table.

`/results/quizzes/{quizPublicId}` provides the basic finalized count, in-progress count, finalized average score, and a 25-row attempt list. The list may filter finalized, submitted, expired, in-progress, or all saved attempts; teacher-local date boundaries are converted to UTC before querying. Distribution charts and question-level aggregate analytics are intentionally omitted. Integrity events remain informational per-attempt observations.

`/results/attempts/{attemptPublicId}` reviews the complete saved attempt in its captured question and choice order, including drafts for an in-progress attempt. The bound immutable paper is authoritative even when live questions are edited or deleted. The page shows lettered choices, submitted answers, answer keys, accepted text, Correct/Partial/Wrong/Unanswered status, response timing, explanations, signed media, captured policy fields, and a chronological integrity timeline. IP address and browser user agent appear only on this owner-authorized detail page. Event metadata is escaped and credential-like keys are removed.

`/results/quizzes/{quizPublicId}/export.csv` applies the same attempt filters without UI pagination and reads database rows in bounded batches. The UTF-8 CSV contains public attempt identity, lifecycle/result fields, timezone-offset timestamps, duration, and integrity-event counts. Spreadsheet-leading formula characters are neutralized. IP addresses, browser agents, answers, event metadata, database IDs, credentials, hashes, passcodes, and private media paths are excluded.

`Teacher\ResultsController` obtains ownership only from `service('auth')->id()`. `TeacherResultsService` owns filter normalization, owner-scoped queries, basic aggregates, timezone conversion, safe attempt projections, and export iteration. A current Practice quiz resolves in Results only when it has historical assessment attempts; malformed, missing, current-Practice-without-history, and non-owned identifiers share the same 404 boundary. Reporting responses are private and non-cacheable.

## Identity and authorization

EduTest owns email/password registration, login, logout, and credential storage. Persistent account, profile, password-hash, and reserved recovery fields live in the single `users` table. The session stores only the authenticated user ID; CI4 file/cache storage holds temporary sessions, CSRF state, and rate-limit buckets.

Passwords use PHP's `PASSWORD_DEFAULT` API and are rehashed when needed. Registration starts a session immediately. Email verification and password recovery are deferred; recovery database fields are reserved but have no routes or UI. Every account is a teacher in the MVP, so there are no role, group, identity, or permission tables.

Teachers directly own quizzes. Authorize nested resources through the complete ownership chain. Public IDs, share tokens, question IDs, option codes, attempt tokens, and signed media URLs are identifiers or credentials, not proof of teacher ownership.

Teacher mutations use session authentication and CSRF protection. A signed 15-minute admission ticket binds quiz version, settings, shuffle seed, and random start identifiers. Assessment bearers are pseudorandom HMAC credentials derived from those identifiers, persisted only as SHA-256 hashes. The browser stores the recovery credential before POST so a lost start response remains recoverable. Practice playback tickets expire after 24 hours and are renewable while valid, without creating an account or result record.

Student requests never use teacher cookies for authorization. A same-origin/custom-header filter, JSON content type, bounded request bodies, and endpoint-level throttling protect the stateless namespace. Session CSRF is exempt only for public student/media paths; teacher and authentication routes retain it. Private wrappers exclude student/media traffic from CI4 page caching and debug-toolbar persistence, including development mode.

## Quiz lifecycle

Quizzes move through draft, published, closed, and archived states and may be soft-deleted. Drafts do not accept students.

Each real start binds the run to an immutable paper for the current `quizzes.revision`. Teachers may edit, delete, reorder, or change the mode afterward; future starts use the newest saved revision and mode while existing assessments, results, and renewable Practice credentials continue from their bound paper. `first_started_at` is historical metadata only.

## Assessment flow

An assessment start validates availability, optional passcode, identity fields, and quiz definition in one transaction. Each deliberate start creates an independent attempt; retrying the same start request returns the existing attempt.

Admission locks the quiz, validates readiness/identity/passcode, creates or reuses the current immutable paper, and initializes `attempts.responses` with the exact presented question/choice order and first active question. The response document has schema version 1, short internal keys, omitted pending/null defaults, string IDs, hexadecimal submission hashes, and exact decimal credit. `AttemptResponses` validates and expands it for player/reporting services; no paper text, answer keys, media, or identity is copied into it.

Sync locks the attempt and accepts ordered batches of up to 100 changed items. Membership, progression, unique submission keys, save versions, immutable submitted answers, and aggregate versions are enforced inside that lock. A successful changed batch writes the entire response JSON and the updated summary/version in one row update. Identical acknowledged retries do not write or increment. Invalid batches roll back. Scores are calculated from the bound paper keys. Finalized responses remain immutable. The public API still exposes the existing `items` arrays and never the internal JSON or submission hashes.

Reports and CSV continue querying indexed summary columns. Only student load/sync/result and authorized teacher detail decode responses. Admission retries, authorization, and integrity-event locks use narrow column selections. Practice creates no attempt or response document. Whole-document writes trade smaller row/index counts for larger writes; measured storage is not uniformly lower. See [ATTEMPT_RESPONSES.md](ATTEMPT_RESPONSES.md) for the format, isolated benchmark, and manual deployment.

The browser advances immediately and drains a serialized outbox in the background. Newer local drafts survive acknowledgements and transient failures. Conflicting immutable submissions pause synchronization and require an explicit choice before replacing local work. Students see provisional results until server confirmation; numeric scores and answer/explanation display follow captured visibility policy. Student database transactions use quiz-first locking, rollback checks, and at most three attempts for MySQL deadlocks/lock timeouts; other errors are not retried.

The optional total timer and captured closing deadline continue through feedback and backgrounding; the earliest deadline wins. There are no per-question deadlines or `question_timeout` submissions. Start/receipt times are server-generated, while offline timer compliance remains client-enforced and unverified. Work arriving after recorded deadlines is accepted with `late_sync`. Abandoned attempts remain incomplete rather than being automatically closed before offline work can arrive. Closed/archived/soft-deleted quizzes still permit existing authorized attempts to finish using their starting policy snapshot.

Opt-in assessment integrity monitoring records `tab_hidden` when the quiz document becomes hidden and `fullscreen_exit` only after fullscreen was active and is then exited. It does not record returns, window focus/blur, or inactivity. The introduction and active player offer non-blocking fullscreen controls; unsupported or denied fullscreen does not prevent starting. Events are idempotent informational signals, never automatic penalties. The tab queue is bounded to 1,000 events; practice sends none.

## Practice flow

Practice is anonymous and non-authoritative. It persists no identity, IP address, browser agent, answer, score, or individual result in the database, server session, cache, or application logs.

Start returns the entire bound paper definition and answer keys for local grading. Practice never posts answers or results. `practice_keys` stores quiz/paper/key/expiry for 24-hour deduplication; expired rows are cleaned in bounded batches during starts. Retry reconstruction uses signed starting settings and seed. Teachers see the aggregate count, not unique people or completions. Throttling stores endpoint counters, not practice identities.

Unused-paper cleanup is reference-based rather than based on the live quiz mode. A paper is eligible only after it has no assessment attempts and no retained Practice keys; changing the live mode therefore cannot strand or prematurely delete a historical paper.

Completed practice results offer “Try again” through a fresh admission/start request, not a local-only reset. New runs honor current availability/settings and configured shuffling, restart timers/progress, and increment the start count. The previous result remains until success. A pending retry ticket is bound to the completed run in tab state so failed/lost responses reuse its deduplication key; successful restarts replace tab state and clear timer warnings. Media renewal responses for an older run cannot overwrite the new run's credentials.

## Scoring

Use the PHP scoring engine for official assessment results and the JavaScript counterpart for instant feedback/practice, with shared fixtures. Each question has fixed maximum credit `1.00`:

- Single choice and short text: `1.00` or `0.00`.
- Multi-select: proportional right-minus-wrong credit, clamped from `0.00` to `1.00`.

Use exact decimal arithmetic and round each multi-select credit half-up to two decimal places before totaling. Credit inside `attempts.responses` is internal per-question credit; attempt `score` is the equivalent correct-question count, `max_score` is the question count, and `percent` remains the percentage. User interfaces describe results as questions, not points.

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
