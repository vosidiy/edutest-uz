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

## Database reference

[schema.sql](schema.sql) is the source of truth for SQL types, indexes, foreign keys, and check constraints. This section explains the application meaning of every table and column. Numeric `id` values are internal database keys and are never accepted as proof of ownership. Public IDs are random external identifiers; credentials and hashes have a separate purpose and must not be exposed. All stored application timestamps are UTC `DATETIME(6)` values and are converted to the teacher's configured timezone only for display.

### `users`

Purpose: stores teacher accounts, their profile preferences, authentication state, and reserved password-recovery state. The MVP has no separate roles or organization tables; every user owns quizzes directly.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal primary key used by sessions and quiz ownership foreign keys. It is compact for joins and is not exposed as a public account identifier. |
| `email` | Teacher login address. Its unique constraint prevents two accounts from using the same normalized email. |
| `password_hash` | One-way PHP password hash used to verify login credentials. The original password is never stored. |
| `display_name` | Human-readable teacher name shown in the dashboard and safe public quiz metadata. |
| `phone` | Optional teacher profile/contact value. It is separate from student phone values stored on assessment attempts. |
| `bio` | Optional teacher biography reserved for the public-profile feature. An empty string avoids nullable text handling. |
| `timezone` | IANA timezone used to interpret teacher-entered filters and render dates. Database timestamps remain UTC. |
| `public_page` | Boolean preference reserving whether a future public teacher page is enabled. It does not make every quiz public. |
| `active` | Boolean account switch that can prevent a disabled account from authenticating without deleting its records. |
| `password_reset_hash` | Optional hash of a password-reset credential. Only a hash is stored so a database leak does not reveal a usable reset token. |
| `password_reset_expires_at` | Expiry paired with `password_reset_hash`; both must be null or both present. Recovery routes are deferred, but the reserved state is structurally safe. |
| `last_login_at` | Time of the latest successful login, useful for account security and administration without reading session files. |
| `created_at` | Account creation time for auditing and stable ordering. |
| `updated_at` | Last persistent account/profile update time. |
| `deleted_at` | Soft-deletion marker. Keeping the row preserves ownership and historical relations while excluding the account from normal use. |

### `quizzes`

Purpose: stores each teacher's mutable working copy, lifecycle state, current published-paper pointer, and lightweight counters. Students never start from these mutable question/settings rows; they start from `current_paper_id`.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal quiz primary key used by questions, papers, attempts, Practice keys, and ownership joins. |
| `user_id` | Owning teacher's internal ID. Every teacher query derives this value from the authenticated session. |
| `public_id` | Random stable identifier used in owner-facing builder, lifecycle, and Results URLs. It avoids exposing sequential `id` values, but authorization is still required. |
| `share_token` | Stable student-facing quiz code used by `/q/{shareToken}`. New values are nine digits; the 64-character capacity preserves legacy links. It is a locator, not an access-control secret. |
| `mode` | Mode of the mutable working copy: `assessment` or `practice`. It affects future students only after Publish changes creates and activates a paper. |
| `status` | Lifecycle state (`draft`, `published`, `closed`, or `archived`) controlling editing/lifecycle actions and whether new starts are allowed. Soft deletion is represented separately by `deleted_at`. |
| `listed` | Boolean author choice for future public-profile listing. A listed quiz is not automatically startable; lifecycle and schedule rules still apply. |
| `title` | Working-copy quiz title used by the builder and copied into a published paper. |
| `description` | Working-copy summary shown on the introduction after publication. |
| `instructions` | Working-copy directions captured in the paper so each run retains the instructions it received. |
| `cover_src` | Private storage locator for the working-copy cover image. Clients receive only authorized signed URLs, never this path. |
| `revision` | Authored-content generation. Start-affecting saves advance it, and publishing stores that value on the immutable paper. It is distinct from concurrency control. |
| `version` | Optimistic-lock version for builder and media mutations. It detects stale teacher writes even when a change does not need to become a new student paper immediately. |
| `current_paper_id` | Nullable pointer to the immutable paper currently served to new students. Null means the quiz has never been published; saved draft changes do not move this pointer. |
| `time_limit_sec` | Optional working-copy whole-quiz timer in seconds. The builder accepts half-minute values and converts them before storage. There is no per-question timer. |
| `opens_at` | Optional UTC time before which new starts are unavailable. It is copied into the published paper. |
| `closes_at` | Optional UTC closing time for new starts and a possible attempt deadline. It must be later than `opens_at` when both exist. |
| `passcode_hash` | Hash of the working-copy admission passcode. It is copied to the private paper column on publish and is never returned to clients; keeping only the hash avoids disclosing the passcode from a database read. |
| `email_mode` | Working-copy rule for assessment email collection: hidden, optional, or required. |
| `phone_mode` | Working-copy rule for assessment phone collection: hidden, optional, or required. |
| `shuffle_questions` | Boolean working-copy policy controlling per-run question order. The actual presented order is persisted on answer rows. |
| `shuffle_options` | Boolean working-copy policy controlling per-run option order. The actual option-code order is persisted per attempt question. |
| `feedback` | Working-copy feedback timing: at the end or after each confirmation. It is captured in the paper for stable run behavior. |
| `show_score` | Boolean presentation policy controlling whether students see their numeric result. Teachers always retain owner-authorized result access. |
| `show_answers` | Boolean presentation policy controlling whether correct answers are shown to students. |
| `show_explain` | Boolean presentation policy controlling explanation display. The schema requires answers to be visible when explanations are visible. |
| `cheat_check` | Boolean assessment policy enabling the two informational integrity events. Practice mode cannot enable it. |
| `practice_starts` | Aggregate count of deduplicated Practice starts. Practice intentionally has no individual attempt/result rows, so this counter is the teacher-visible activity measure. |
| `published_at` | Time of the first successful publication. It records lifecycle history while later published revisions are identified by papers and revisions. |
| `created_at` | Working-copy creation time. |
| `updated_at` | Last quiz-level authoring, lifecycle, or media update time used for library ordering and display. |
| `deleted_at` | Soft-deletion marker used by Trash and historical reporting. The quiz and retained papers remain available to authorized historical attempts/results. |

### `questions`

Purpose: stores the ordered questions in the teacher's mutable working copy. Rows may be edited or deleted after publication because historical runs use `quiz_papers` and `attempt_answers`.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal question key. It is also captured as the historical question identifier in papers/answer rows, but `attempt_answers` deliberately has no foreign key back to this mutable row. |
| `quiz_id` | Parent working-copy quiz and ownership path for every question operation. |
| `pos` | One-based question order in the working copy. Uniqueness within a quiz prevents ambiguous ordering. |
| `type` | Answer model: `single_choice`, `multi_select`, or `short_text`. It determines validation and scoring. |
| `content` | Escaped plain-text question prompt copied into an immutable paper at publication. |
| `media_type` | Optional media kind (`image`, `audio`, or `video`) used to validate and render `media_src` correctly. |
| `media_src` | Optional private file locator or normalized supported video URL. Its paired constraint prevents a source without a type or vice versa. |
| `explanation` | Optional answer explanation captured in the paper and displayed only according to published visibility settings. |
| `text_answers` | JSON array of accepted short-text answers. JSON is appropriate here because this is an authored list read and replaced with the question, not independently queried reporting data. |
| `created_at` | Question creation time for persistence/audit consistency. |
| `updated_at` | Last working-copy question update time. |

### `question_options`

Purpose: stores the ordered choices and answer key for choice questions in the mutable working copy.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal option primary key used by authoring and media ownership checks. |
| `question_id` | Parent working-copy question. It supplies the ownership chain through question to quiz to teacher. |
| `pos` | One-based working-copy option order, unique within the question. |
| `code` | Random client-facing option identifier used in papers and student answers instead of exposing correctness flags. It need only be unique within its question. |
| `content` | Escaped plain-text choice label. An image-backed choice may also use its media fields. |
| `media_type` | Optional option media kind. The schema intentionally permits only `image`. |
| `media_src` | Optional private option-image locator. Signed delivery prevents revealing this path to clients. |
| `is_correct` | Boolean authoring answer key copied into the paper definition. Official grading reads the bound paper, not a client-reported value. |
| `created_at` | Option creation time for persistence/audit consistency. |
| `updated_at` | Last working-copy option update time. |

### `quiz_papers`

Purpose: stores immutable, explicitly published quiz versions. A paper is the authoritative definition received by new starts while it is current and by historical runs permanently while referenced.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal paper primary key used by current-quiz, attempt, and Practice-key foreign keys. |
| `quiz_id` | Parent quiz. Composite foreign keys use it to guarantee that a selected paper belongs to the same quiz as its pointer/run. |
| `public_id` | Random opaque paper identifier used in signed media descriptors and safe server/client projections without revealing `id`. |
| `revision` | Authored revision captured by this paper. Uniqueness per quiz prevents two different immutable definitions for the same revision. |
| `passcode_hash` | Private published admission hash. It freezes the passcode policy for new starts on this paper without placing the hash in `definition` or any API response. |
| `definition` | Server-owned schema-versioned JSON containing the exact published metadata, settings, questions, option order/codes, answer keys, accepted text answers, media references, and scoring policy. It excludes credentials, identities, responses, scores, and expiring URLs. |
| `created_at` | Publication time of this immutable paper, useful for history and cleanup decisions. |

### `attempts`

Purpose: stores one Assessment run's identity, authorization binding, paper reference, authoritative deadline, lifecycle, and summary result. Per-question data belongs in `attempt_answers` so the attempt row remains small and reportable.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal attempt primary key used by answer and integrity-event foreign keys. |
| `quiz_id` | Parent quiz for owner-scoped reporting and start-key uniqueness. Historical access remains valid after the live quiz changes. |
| `paper_id` | Immutable paper received by this run. The composite foreign key ensures it belongs to `quiz_id`. |
| `public_id` | Random attempt identifier used in bearer-protected student URLs/API calls and owner-authorized teacher detail URLs without exposing sequential `id`. It is an identifier, not the bearer secret. |
| `token_hash` | SHA-256 hash of the high-entropy bearer credential. The server authenticates requests by hashing the supplied token; a usable token is never stored in the database. |
| `start_key` | Random retry/idempotency key bound to a quiz. Its unique constraint makes a lost/repeated start request return the first successfully created attempt instead of creating a duplicate. |
| `shuffle_seed` | Stable random seed used to reproduce this run's published shuffle decisions and retry responses. Exact presented option order is also persisted in answer rows. |
| `name` | Required student-supplied assessment display name captured at the first successful start. |
| `email` | Optional/required assessment email according to the paper's admission policy; null when hidden or omitted. |
| `phone` | Optional/required assessment phone according to the paper's admission policy; null when hidden or omitted. |
| `ip` | Start/request IP captured for owner-authorized attempt detail and informational review. It is excluded from CSV and is not student identity enforcement. |
| `agent` | Browser user-agent captured for owner-authorized device context. It is excluded from CSV and does not fingerprint or limit attempts. |
| `status` | `in_progress` is active; `abandoned` is a provisional result from received work at a known deadline; `completed` means the final browser record and all declared confirmations were accepted. Only Completed is permanently final. |
| `started_at` | Authoritative server start time used for duration and timed-deadline calculation. |
| `last_activity_at` | Server receipt time of accepted activity/answer/finalization work. It is audit context, not the basis for extending offline inactivity. Loading, media, and integrity traffic do not extend the deadline. |
| `client_activity_at` | Latest accepted client-reported meaningful activity. Untimed expiry is eight hours after this occurrence time, capped by published closing time. Delayed receipt does not itself extend it. Historical rows are backfilled from existing activity. |
| `late_sync` | Boolean flag identifying delayed work that reconciles an expired/abandoned run. Teacher lists, detail, and CSV expose this informational flag without score penalties. |
| `expires_at` | Current authoritative deadline. For timed quizzes it is the earliest timer/schedule deadline; for untimed quizzes the inactivity deadline may move forward but is capped by closing time. |
| `deadline_reason` | Reason that will apply if `expires_at` is reached: timer expiry, scheduled close, or stale inactivity. Storing it makes lazy finalization deterministic. |
| `finished_at` | Effective completion/timeout occurrence time, not upload receipt time. It is null while in progress. Client-reported completion is bounded and checked against the captured deadline. |
| `ended_reason` | Normal completion, explicit student quit, timer expiry, scheduled close, or inactivity. A Completed run may have `quit` or a timeout reason; status describes synchronization finality, not whether every question was reached. |
| `score` | Nullable integer count of correct questions. It is populated only for terminal attempts and is calculated by the server from the paper and confirmed rows. |
| `max_score` | Question count for this paper/run. It is known at start and provides the denominator without decoding the paper for list/report queries. |
| `percent` | Nullable exact two-decimal percentage for terminal reporting, filtering, sorting, and CSV without recalculating every row. |

### `attempt_answers`

Purpose: stores one initialized row for every question presented in an Assessment run. Independent immutable confirmations make retries safe and preserve complete history without repeatedly replacing one large JSON document.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal answer-row primary key. Business uniqueness is separately enforced by attempt/question and attempt/position constraints. |
| `attempt_id` | Parent assessment. Deleting an attempt cascades to its answers so orphaned response data cannot remain. |
| `question_id` | Historical numeric question identifier copied from the paper. It intentionally has no foreign key to mutable `questions`, allowing the teacher to delete/recreate live rows without breaking results. |
| `pos` | One-based presented question position after shuffling. It provides exact review/resume order and is unique within the attempt. |
| `presented_option_codes` | JSON array preserving the exact shuffled option-code order so A/B/C labels in historical review match what the student saw. It is an empty array for short-text questions. |
| `status` | Per-question state: `not_reached`, `answered`, or `skipped`. A row moves from `not_reached` only once; confirmed rows are immutable. |
| `selected_option_codes` | JSON array of confirmed choice codes for single/multi-select answers. It is null for not-reached, skipped, and short-text rows. |
| `text_answer` | Confirmed short-text response. It is null for choice, skipped, and not-reached rows. |
| `is_correct` | Nullable server-calculated grade. Null means not reached; confirmed answered/skipped rows store `1` or `0`, preserving the official historical outcome. |
| `answered_at` | Server receipt time of the immutable confirmation/skip. It supports chronological review without trusting the browser clock. |
| `client_answered_at` | Browser-reported confirmation occurrence time used to validate delayed uploads against the run deadline. Nullable for historical rows, whose unknown occurrence time is never invented. |

### `cheat_events`

Purpose: stores optional, informational integrity events for Assessment attempts. Events never change grades automatically and Practice creates none.

| Column | Purpose and why it is needed |
| --- | --- |
| `id` | Internal event primary key. |
| `attempt_id` | Parent assessment and authorization path to the owning teacher. |
| `event_key` | Client-generated idempotency key. Uniqueness within an attempt prevents a retried event from appearing twice. |
| `type` | Allowed observation: `tab_hidden` or `fullscreen_exit`. Restricting values keeps the timeline meaningful and rejects obsolete event types. |
| `happened_at` | Optional client-reported UTC occurrence time. It may be unavailable/offline and is therefore not the authoritative receipt time. |
| `received_at` | Server receipt time used for reliable ordering and audit context. |
| `duration_ms` | Optional non-negative duration associated with an event when relevant. The current two event types may leave it null. |
| `data` | Bounded JSON metadata for safely extensible event context. Teacher output is escaped/redacted, and credentials must never be stored here. |

### `practice_keys`

Purpose: provides short-lived idempotency for anonymous Practice starts and retains the exact paper used by a renewable Practice run. It does not store identity, answers, progress, score, IP, or browser details.

| Column | Purpose and why it is needed |
| --- | --- |
| `quiz_id` | Parent quiz and first part of the composite primary key. It scopes a retry key and supports aggregate counter updates. |
| `paper_id` | Immutable paper used for the Practice start. It makes retry/media reconstruction stable after the teacher republishes or changes mode. |
| `request_key` | Random retry/idempotency key. Reusing it for the same quiz reconstructs the same start instead of incrementing `practice_starts` twice. |
| `expires_at` | Retention/credential expiry. Expired keys are cleaned in bounded batches and stop retaining otherwise unused Practice-only papers. |

### Database relationships and invariants

- Ownership is `users.id -> quizzes.user_id`; questions/options follow the nested quiz ownership chain.
- `quizzes.current_paper_id`, `attempts.paper_id`, and `practice_keys.paper_id` use same-quiz composite foreign keys, preventing a paper from another quiz being attached accidentally.
- Mutable authoring rows may change freely; retained papers plus historical IDs/order in `attempt_answers` keep existing runs readable.
- Assessment answer rows are initialized transactionally with the attempt and become immutable after confirmation. Attempt summary fields are updated only by server-owned grading/finalization logic.
- Boolean, lifecycle, media-pair, schedule, terminal-state, and answer-state checks reject structurally inconsistent records even if application validation is bypassed.

## Frontend

Server-rendered PHP is the default. Pages share accessible layouts, navigation, flash messages, form errors, and responsive CSS.

- The unified teacher dashboard and quiz-scoped assessment reports use server-rendered views with small plain-JavaScript enhancements.
- The quiz builder uses the pinned local Vue 3.5.43 global production build without single-file components, Vite, or an SPA router. Its application script URL includes the file modification version so an updated template cannot execute against stale cached builder code.
- Student assessment and practice pages use plain JavaScript modules and server APIs.
- Render authored content as escaped plain text. The introduction contains no answer keys; a successful start deliberately preloads keys for both feedback modes. Private media paths are never sent.
- Support keyboard use, visible focus, responsive layouts, clear save/retry states, and screen-reader-friendly status updates.

The teacher workspace keeps its light-theme design system in `public/css/teacher.css`: tokens, reset, accessibility utilities, and reusable components. Student introduction, player, and results pages load only `public/css/player.css`, which contains their own light-theme tokens, reset, accessibility utilities, components, and responsive layouts; it does not load or import teacher styles. Public CSS, JavaScript, fonts, and images live directly under the matching top-level directories in `public/`; the pinned Vue production build lives at `public/js/vue.global.prod.js`. Student player CSS and JavaScript URLs include the file modification version so browser caches refresh after deployed player changes. Hosted video iframes opt into origin-only cross-origin referrers so YouTube/Vimeo embeds work while player APIs and signed media fetches remain no-referrer. The Uzbek marketing landing has independent, versioned CSS/JavaScript, self-hosted Inter fonts, and public presentation images; its account actions send authenticated teachers directly to the dashboard. Authentication and framework error pages retain independent styling. Student state/scoring/synchronization are plain JavaScript modules; no frontend build or runtime Node service is required.

Browser state is limited by mode:

- Assessments store the attempt ID, bearer credential, published paper payload, local progress, and retry queue in same-origin `localStorage` under the quiz share code. This lets the same browser resume after refresh or restart. Server-side deadlines and confirmed answer rows remain authoritative.
- Practice stores its progress and review data in the browser for the current share code only; clearing browser storage loses the session.

### Implemented teacher authoring

Successful login and registration lead to `/dashboard`, the single teacher home. Its centered, approximately 1280px-wide layout uses a top header with the EduTest dashboard link and a keyboard-accessible account disclosure containing CSRF-protected logout. There is no sidebar or mobile bottom navigation. Authenticated compatibility routes `/quizzes`, `/quizzes/archived`, and `/quizzes/trash` redirect to the corresponding dashboard view; `/results` redirects to All with assessment-report eligibility enabled. Only recognized search, mode/status, lifecycle, sort, and page parameters are translated.

The dashboard combines active/published quiz counts and finalized/in-progress assessment totals with one enriched quiz library. Assessment metrics include archived/deleted history; quiz counters retain their existing non-deleted scope. The list supports title search, status/mode/report-eligibility filters, safe sorting, 20-row pagination, and All/Active/Archived/Trash views (Active by default). Each row shows activity, average score, Practice starts, latest submission, current lifecycle/mode, and eligible Builder/Responses links with lifecycle actions. Separately grouped question/attempt summaries avoid multiplying counts or reading response JSON and papers; displayed dates use the teacher timezone. Metrics are workspace-wide and independent of list filters. Empty averages are shown as unavailable, not zero. Dashboard responses are private and non-cacheable.

The creation dialog accepts an optional image cover with local preview. It creates the draft, loads its version, and uploads through the existing private-cover API using serialized CSRF-aware mutations before opening the builder. Upload failure retains the draft identifier for retry or continuation without a cover; cancellation does not delete a saved draft. Cancel and × submit a separate native `method="dialog"` form without validation, independent of creation/close handlers. Close-event cleanup restores opener focus and clears temporary preview state. Requests temporarily disable editing and dismissal. The teacher script URL includes its file modification version to refresh browser caches after updates.

The builder uses the locally vendored Vue 3.5.43 global production build and same-origin JSON endpoints under `/api/v1`. Builder, per-quiz Responses, and attempt detail share a sticky `builder-bar` partial in a quiz workspace without the dashboard brand/profile header. Builder actions and Vue bindings remain builder-only; Responses keeps CSV export and attempt detail keeps Back to responses. Archived/deleted report headers disable Builder and link to the appropriate dashboard restoration view without restricting historical review. Desktop navigation is centered; tablet/mobile navigation wraps below the title, and essential Back/Save actions remain reachable. The shared header provides direct Builder/Responses navigation for current Assessment quizzes and for Practice quizzes that retain historical assessment attempts; Practice quizzes without individual records omit that navigation. Availability and the owner-protected Results URL are read-only fields derived from the last saved server document, so unsaved mode edits cannot create an invalid report link. One “Add question” action creates a two-choice single-choice question; teachers can change it immediately to multi-select or short text. Choice correctness uses radio controls for single choice and checkboxes for multi-select, and choices are lettered `A)`, `B)`, … in their displayed order. Questions have equal weight and no individual timer. The optional total timer is authored as `timeLimitMinutes` in half-minute steps and converted to whole seconds for storage/runtime. Answer options accept image media only; question media retains image, audio, and validated video. Teacher preview renders the current document without grading, starting an attempt, or exposing it publicly.

The builder alone fills the dynamic viewport (with a `100vh` fallback) through a flex/grid height chain, with a naturally sized persistent header. Above 1180px, the question list, editor, and settings content scroll independently; rail headings and Add question remain fixed. At narrower widths, labelled Questions/Editor/Settings controls show one panel without unmounting it or losing its scroll position. Question selection/addition reveals the editor at its top. Each scrolling region is keyboard-focusable and labelled. Existing-attempt, upload-limit, readiness, and error notices live inside the editor; new errors reveal that panel. A collapsed “About answers & timers” disclosure retains the answer-key/offline-timing explanation at its bottom. Responses and dashboard layouts are unaffected.

The header title opens a native Quiz details dialog containing title and cover controls, removed from Settings. Each opening edits a temporary copy; Cancel, ×, and Escape discard only that opening's edits and restore title-button focus. Apply validates the title and stages an optional image upload/removal locally. Preview object URLs remain alive while used by the dialog or pending cover and are revoked when no longer needed. File type, size, and decoded dimensions are checked in the browser; the existing server media validation remains authoritative.

The save button is neutral/disabled when Saved, primary when changes remain, disabled during Saving, and reopens the existing dialog for Resolve conflict. One save pipeline first acknowledges the aggregate and then persists its staged cover using the returned version through the serialized CSRF helper. These are separate transactions: a failed cover keeps its local preview/operation for retry without resaving an unchanged aggregate. Saved and the navigation warning include both content and pending media. A three-way acknowledgement merge preserves newer text/settings, additions, deletions, and reordered row identities made during requests. Reload conflict discards staged work only after a successful load; explicit overwrite retains it. In-flight lifecycle responses also preserve newer local edits.

The builder saves one versioned quiz document containing settings, questions, options, accepted text answers, and safe media descriptors. Draft changes autosave one second after the last edit; published, closed, and archived quizzes require an explicit save so a temporary incomplete edit is not submitted automatically. The draft Publish action is disabled until the local document meets publication-readiness rules, while server validation remains authoritative. Invalid manual saves enter a visible validation-error state instead of being persisted. Mutations are serialized so regenerated CSRF tokens remain ordered, and navigation warns while changes are pending. The service locks the owned quiz, compares its version, validates the complete aggregate and nested ownership, and writes quiz, question, and option changes in one transaction. A version mismatch returns `409` and pauses saving until the teacher reloads the server document or explicitly overwrites it.

Passcodes use explicit `unchanged`, `set`, and `clear` operations. Only the configured/not-configured state is returned to the client; a passcode value or hash is never returned. IDs, share tokens, and option codes are generated on the server. Newly created and duplicated quizzes receive collision-retried nine-digit share codes; legacy 64-character hexadecimal links remain accepted. Share codes locate public introductions and are not authorization secrets.

Lifecycle changes are service-owned: drafts publish only after full content validation; published quizzes close and closed quizzes reopen; any active quiz may be archived. Unarchiving returns a never-published quiz to draft and a quiz with a published paper to closed. Published quizzes must first be closed or archived before soft deletion, and restoration preserves the stored lifecycle status. Duplication creates an unstarted, unlisted draft with new identifiers, zero activity, and independent media copies.

Initial Publish and later Publish changes create immutable `quiz_papers` rows for the current authored revision and update `quizzes.current_paper_id`. Saving the working copy does not change what students receive. Content, settings, and mode remain editable; future starts use the current published paper, while existing assessment attempts and practice keys stay bound to the paper they received.

`quiz_papers` is unique by `(quiz_id, revision)` and stores a server-owned schema-versioned JSON definition without credentials, identity, responses, scores, or expiring URLs. Paper schema version 3 contains published metadata, settings, questions, option codes/order, correct answers, accepted text answers, media references, total timer/schedule policy, and the all-or-nothing scoring policy. The published passcode hash is stored in `quiz_papers.passcode_hash`, never in the JSON definition or any API response. `attempts.paper_id`, `practice_keys.paper_id`, and `quizzes.current_paper_id` use same-quiz foreign keys. Historical question IDs and exact presented order live in `attempt_answers`, with no dependency on mutable question rows.

Published and closed quizzes have a stable `/q/{shareToken}` introduction with safe metadata, optional cover, instructions, admission fields, and availability. The route accepts current nine-digit codes and preserved 64-character hexadecimal links. Draft, archived, deleted, malformed, and unknown introduction tokens return 404. `/q/{shareToken}/play` and `/q/{shareToken}/results` are credential-free shells; actual attempt data requires the tab's bearer. These shells do not gate an existing attempt on current quiz availability.

### Implemented teacher reporting

The reporting overview is integrated into `/dashboard`; `/results` is a compatibility redirect rather than a separate screen. The report-eligible filter includes every current Assessment quiz and any owned quiz with historical assessment attempts, even when its current mode is Practice or it is archived/soft-deleted. Workspace totals and per-quiz summaries share the dashboard library rather than duplicating it in a second results table.

`/results/quizzes/{quizPublicId}` provides the basic completed count, in-progress count, abandoned count, completed-average percentage, and a 25-row attempt list. The list may filter completed, abandoned, in-progress, or all saved attempts; teacher-local date boundaries are converted to UTC before querying. Distribution charts and question-level aggregate analytics are intentionally omitted. Integrity events remain informational per-attempt observations.

`/results/attempts/{attemptPublicId}` reviews the complete saved attempt in its captured question and choice order. The bound immutable paper is authoritative even when live questions are edited or deleted. The page shows lettered choices, submitted answers, answer keys, accepted text, Correct/Wrong/Unanswered status, explanations, signed media, captured policy fields, answer timestamps, IP/device details, and a chronological integrity timeline. Not-yet-reached rows remain visible for abandoned attempts. Event metadata is escaped and credential-like keys are removed.

`/results/quizzes/{quizPublicId}/export.csv` applies the same attempt filters without UI pagination and reads database rows in bounded batches. The UTF-8 CSV contains public attempt identity, lifecycle/result fields, timezone-offset timestamps, duration, and integrity-event counts. Spreadsheet-leading formula characters are neutralized. IP addresses, browser agents, answers, event metadata, database IDs, credentials, hashes, passcodes, and private media paths are excluded.

`Teacher\ResultsController` obtains ownership only from `service('auth')->id()`. `TeacherResultsService` owns filter normalization, owner-scoped queries, basic aggregates, timezone conversion, safe attempt projections, and export iteration. A current Practice quiz resolves in Results only when it has historical assessment attempts; malformed, missing, current-Practice-without-history, and non-owned identifiers share the same 404 boundary. Reporting responses are private and non-cacheable.

Per-quiz Responses defaults to all statuses and newest starts. Its displayed page receives one grouped `attempt_answers` count query limited to those attempt IDs, exposing confirmations/skips received and correct confirmations so far without loading answer bodies or paper JSON. Unfinished attempt score columns stay null and do not enter completed averages or score filters. Quit runs retain database status `completed` for synchronization finality but render as Quit and remain in finalized filters/aggregates. Reports explicitly warn that In progress is not online presence and that browser-local offline work is unknown until upload; refresh is page-based, without polling.

## Identity and authorization

EduTest owns email/password registration, login, logout, and credential storage. Persistent account, profile, password-hash, and reserved recovery fields live in the single `users` table. The session stores only the authenticated user ID; CI4 file/cache storage holds temporary sessions, CSRF state, and rate-limit buckets.

Passwords use PHP's `PASSWORD_DEFAULT` API and are rehashed when needed. Registration starts a session immediately. Email verification and password recovery are deferred; recovery database fields are reserved but have no routes or UI. Every account is a teacher in the MVP, so there are no role, group, identity, or permission tables.

Teachers directly own quizzes. Authorize nested resources through the complete ownership chain. Public IDs, share tokens, question IDs, option codes, attempt tokens, and signed media URLs are identifiers or credentials, not proof of teacher ownership.

Teacher mutations use session authentication and CSRF protection. A signed 15-minute admission ticket binds quiz version, settings, shuffle seed, and random start identifiers. Assessment bearers are pseudorandom HMAC credentials derived from those identifiers, persisted only as SHA-256 hashes. The browser stores the recovery credential before POST so a lost start response remains recoverable. Practice playback tickets expire after 24 hours and are renewable while valid, without creating an account or result record.

Student requests never use teacher cookies for authorization. A same-origin/custom-header filter, JSON content type, bounded request bodies, and endpoint-level throttling protect the stateless namespace. Session CSRF is exempt only for public student/media paths; teacher and authentication routes retain it. Private wrappers exclude student/media traffic from CI4 page caching and debug-toolbar persistence, including development mode.

## Quiz lifecycle

Quizzes move through draft, published, closed, and archived states and may be soft-deleted. Drafts do not accept students.

Students start from `quizzes.current_paper_id`, not from mutable builder rows. Teachers may edit, delete, reorder, or change the mode afterward; those changes affect students only after Publish changes creates a new paper. Existing assessments, results, and renewable Practice credentials continue from their bound paper.

## Assessment flow

An assessment start validates availability, optional passcode, identity fields, and the current published paper in one transaction. Each deliberate start creates an independent attempt. Admission retries follow “first successful start wins”: under the quiz-row lock, the service finds an existing attempt by quiz/start key and verifies its public ID and stored bearer hash against the signed ticket. It returns that attempt before inspecting changed or omitted identity/passcode input. An unfinished attempt resumes; a Completed attempt returns its immutable result. Provisional Abandoned results may still reconcile saved offline work. Original identity, paper, timestamps, and scores are unchanged. Expired/tampered tickets remain invalid, and new starts retain every admission check. Failed starts consume no start key.

Admission creates one `attempt_answers` row per paper question in the start transaction. Each row stores the historical question ID, presented position, presented option-code order, status, confirmed selected option codes or text answer, server-computed correctness, and `answered_at`. Rows start as `not_reached` and may move once to `answered` or `skipped`; changed retries receive `answer_locked`, while identical retries are no-ops. Finish validates the declared confirmed count and presented prefix, then recalculates the official score directly from the bound paper. Normal completion requires every row; timeout and explicit Quit permit an unanswered suffix. Practice creates no attempt or answer rows.

The browser advances immediately and drains a serialized confirmation queue in the background. Confirmed answers use `PUT /api/v1/player/assessments/{attemptId}/answers/{questionId}`; finish uses `POST /api/v1/player/assessments/{attemptId}/finish`. Only confirmed answers or explicit skips are persisted; unconfirmed selections remain local. Confirmations for different questions are independent and safe if delayed or retried out of order. Students see provisional results until server confirmation; numeric scores and answer/explanation display follow captured visibility policy. Student database transactions use quiz-first locking, rollback checks, and at most three attempts for MySQL deadlocks/lock timeouts; other errors are not retried.

Resume position is derived from the first `not_reached` row. There is no duplicate attempt-position column, aggregate response document, aggregate attempt version, or `currentPosition` API property. Reports and CSV query indexed attempt summary columns; authorized attempt detail combines the bound paper with `attempt_answers`. See [ATTEMPT_RESPONSES.md](ATTEMPT_RESPONSES.md) for the format, isolated benchmark, and manual deployment.

The plain-JavaScript player acquires a non-waiting exclusive Web Lock named for the quiz share code before starting, recovering, or resuming either mode. A second same-origin tab displays a Retry notice and cannot answer, sync, submit integrity events, or renew media. Ownership survives backgrounding/offline work and ambiguous start failures. Assessment completion releases it only after server confirmation and pending sync; Practice releases it after local completion, and Try again reacquires. Page exit releases the lock; refresh/back-forward restoration reacquires before activation. Introduction-only and synchronized-results views do not reserve a quiz (media renewal acquires temporary ownership). Unsupported/denied APIs fail open with a notice. The lock itself carries no credentials, responses, or identity; progress and credentials remain in the established same-origin localStorage record, never in cross-tab messages. This is accidental-duplicate prevention while a tab is open, not a per-person limit: other profiles, private sessions, browsers, and devices remain independent.

The optional total timer and captured closing deadline continue through feedback, backgrounding, and tab closure; the earliest deadline wins and is the only countdown shown. Untimed expiry is eight hours after meaningful browser interaction (`client_activity_at`), capped by closing, but this internal inactivity countdown and advance warning are not rendered. Selection/typing, Submit, Skip, Next, and explicit Resume before expiry count; passive visibility and network retries do not. At expiry, the browser saves a timeout final record and scores only confirmed answers, leaving an unsubmitted draft unanswered.

Every attempt endpoint resolves overdue state under its row lock. Abandonment scores only received rows and remains provisional. Delayed confirmations may recover it using captured `clientAnsweredAt`/`clientActivityAt`; receipt after the deadline is allowed, answering after it is not. Untimed reported activity may restore In progress. A valid final record makes the run Completed, including timeout endings, and it can no longer reopen. Offline occurrence/activity claims cannot be independently verified. UTC timestamps must be no earlier than start and no more than five seconds ahead of server time. Invalid actions do not roll back a legitimate lazy abandonment. Teacher report access also resolves expiry; dashboard totals may remain stale until touched.

Confirmation responses are small acknowledgements, not full papers. Official grading reads the raw bound question/answer key without shuffling or media signing. `POST assessments/{attemptId}/activity` coalesces untimed meaningful activity at most once per minute while changes exist; this routine traffic does not produce pending-upload UI. The authenticated full Assessment load includes only the saved student's name/email projection, while small acknowledgements and public metadata omit it. Finish carries reason, occurrence/activity times, and confirmed count; missing confirmations produce retryable `incomplete_sync`. `finishReason=quit` finalizes any confirmed prefix (including zero), stores status Completed with ended reason Quit, and permits fresh admission only after acknowledgement. An expired browser records its deadline reason instead. Identical answer/Finish retries are harmless; changed confirmed answers or final records are rejected. Late integrity events within the recorded duration remain informational and do not extend activity.

The effective runtime timing policy is `client_deadline_late_sync_v1`. Existing immutable paper JSON is not rewritten; runtime settings expose the current timing policy while retaining each paper's published timer, closing time, and other authored settings.

Opt-in assessment integrity monitoring records `tab_hidden` when the quiz document becomes hidden and `fullscreen_exit` only after fullscreen was active and is then exited. It does not record returns, window focus/blur, or inactivity. The introduction and active player offer non-blocking fullscreen controls; unsupported or denied fullscreen does not prevent starting. Events are idempotent informational signals, never automatic penalties. The tab queue is bounded to 1,000 events; practice sends none.

## Practice flow

Practice is anonymous and non-authoritative. It persists no identity, IP address, browser agent, answer, score, or individual result in the database, server session, cache, or application logs.

Start returns the entire current published paper definition and answer keys for local grading. Practice never posts answers or results. `practice_keys` stores quiz/paper/key/expiry for 24-hour deduplication; expired rows are cleaned in bounded batches during starts. Retry reconstruction uses signed starting settings and seed. Teachers see the aggregate count, not unique people or completions. Throttling stores endpoint counters, not practice identities.

Unused-paper cleanup is reference-based rather than based on the live quiz mode. A paper is eligible only after it is no longer current and has no assessment attempts or retained Practice keys; changing the live mode therefore cannot strand or prematurely delete a historical paper.

Completed practice results offer “Try again” through a fresh admission/start request, not a local-only reset. New runs honor current availability/settings and configured shuffling, restart timers/progress, and increment the start count. The previous result remains until success. A pending retry ticket is bound to the completed run in tab state so failed/lost responses reuse its deduplication key; successful restarts replace tab state and clear timer warnings. Media renewal responses for an older run cannot overwrite the new run's credentials.

## Scoring

Use the PHP scoring engine for official assessment results and the JavaScript counterpart for instant feedback/practice, with shared fixtures. Each question is all-or-nothing:

- Single choice and short text are correct or wrong.
- Multi-select requires the exact correct set; partial selections are wrong.
- Skipped and not-reached questions are unanswered.

Attempt `score` is the integer number of correct questions, `max_score` is the question count, and `percent` remains the exact percentage to two decimal places. User interfaces describe results as questions, not points.

## Media

Questions may reference one image, audio file, or supported external video; answer options and quiz covers are image-only. Uploaded files remain outside `public/` under random paths; decoded images are limited to 16 megapixels before processing.

Validate file size, decoded content/MIME, and ownership. Re-encode images where required, reject unsafe formats, and serve private files only through short-lived signed URLs. A stored private path must never appear in a client response.

The authoring implementation stores random quiz media below `writable/uploads/quiz-media`. JPEG, PNG, and WebP images up to 5 MiB are decoded and re-encoded to strip metadata; supported question audio up to 20 MiB is content-validated and stored with a server-selected extension. YouTube, Vimeo, and direct HTTPS MP4 question URLs are normalized rather than accepted as arbitrary embed markup. Answer-option endpoints reject audio and video. Media mutations require a persisted owned target, follow the complete ownership chain, compare the current quiz version, and increment it transactionally.

Failed media writes remove newly created files. Replaced or deleted files are cleaned after the database commit, while soft-deleted quiz media is retained for restoration. Duplicated quizzes receive physically independent copies of uploaded media.

Private images and audio are delivered through expiring HMAC-signed `/media/{token}` URLs with range support and `nosniff`/private-cache headers. Signing uses the untracked `encryption.key` environment value; database responses contain a temporary playback URL, never `media_src`.

Cover mutations use the same ownership/version/cleanup rules and physically independent duplication. Media changes and starts serialize under the quiz lock. Paper descriptors use an opaque media key and paper public ID; signed delivery resolves the current paper record without exposing its stored path. Replaced files are deleted only when neither live rows nor retained papers reference them. Assessment papers remain while attempts reference them; expired practice-only papers are eligible for bounded cleanup. The browser keeps a bounded in-memory image cache; unloaded audio/video and reloads are not guaranteed offline.

## API and errors

The authoring API provides CSRF recovery; quiz create/load/save; lifecycle and duplication; and cover/question/option media replacement and removal. All routes require the teacher session and mutations remain CSRF-protected.

The separate `/api/v1/player` API provides admission tickets, starts, bearer-authenticated assessment load/answer/activity/finish/results/integrity/media, and stateless practice media renewal. Its envelopes use timestamp metadata only, with no session or CSRF token generation. See [STUDENT_PLAYER.md](STUDENT_PLAYER.md) for endpoints, schema expectations, and verification.

Responses use `{data, meta}` and `{error, meta}` envelopes with camelCase fields. Timestamps are UTC ISO strings, decimals are exact strings, and numeric database IDs are serialized as strings for JavaScript safety. Authoring services derive ownership only from the authenticated session and ignore submitted ownership identifiers.

Return appropriate 4xx errors for validation, authentication, authorization, missing resources, expired credentials, and version conflicts. Do not expose stack traces, SQL, secrets, or internal filesystem paths.

### Offline browser recovery

Persistence format 4 saves selections, confirmations, timing, position, and queued requests in localStorage. Each confirmation and operation are saved together before advancing. Local recovery precedes network reconciliation; incoming Abandoned state cannot erase pending work, drafts, or provisional results. One coordinator serializes recovery and outgoing requests, removing only acknowledged operations while retaining newer edits. Older unsupported queues are preserved with an actionable notice rather than discarded or assigned invented timestamps.

Feedback/results use preloaded keys immediately. All visibility flags off means receipt-only UI. Red connection warnings remain after offline/network failures until communication succeeds; bounded retries and manual Retry retain work. Storage failures fall back to memory with a restart warning. A new Assessment is unavailable until the current final record and required uploads are acknowledged. Reopening needs connectivity; no service worker or guaranteed offline media/page reload exists. Screenshots of permitted local results are informal evidence, never an automatic change to the official record.

## Operations

Local development uses MAMP and phpMyAdmin. Production targets cPanel/shared hosting with TLS, SMTP, cron, backups, and `public/` as the preferred document root.

Keep secrets in `.env`, writable data private, production errors hidden, and ordinary logs free of credentials, tokens, signed URLs, and practice bodies. Back up the database and media before applying SQL changes and test restore procedures before launch.

Authoring deployments require an untracked `encryption.key`. To support the documented 20 MiB audio limit, the PHP web runtime must set `upload_max_filesize` above 20 MiB and `post_max_size` above that value; the builder reports a configuration warning when the effective runtime limits are lower.

The developer reported applying the response-storage, short-code/integrity, and MVP attempt-cleanup SQL on 2026-09-29. Their one-time scripts have been removed. The published-paper/normalized-answer reset is documented in [published-papers-attempt-answers-reset.sql](published-papers-attempt-answers-reset.sql) for disposable development data and is never executed by the application. Fresh databases import only the canonical [schema.sql](schema.sql). Existing normalized-answer databases use the additive [offline-continuity-upgrade.sql](offline-continuity-upgrade.sql), with traffic paused and a backup. It preserves attempts and answers, backfills client activity from existing activity, and leaves unknown historical answer occurrence times null. After that prerequisite, apply the independent non-destructive [quit-attempt-upgrade.sql](quit-attempt-upgrade.sql) with traffic paused and a backup; it replaces only the ended-reason check and preserves every record. Deploy its matching application code at the same time. The older destructive reset predates these additive changes and must be followed by both applicable upgrades if used; do not run that reset for an ordinary deployment.
