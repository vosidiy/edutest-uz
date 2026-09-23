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
  Services/{Authoring,Assessment,Practice,Reporting,Media,Identity}/
  Models/
  Views/{layouts,teacher,student,public}/
  Commands/
```

- Controllers parse requests, enforce the authenticated entry point, call one application service, and format a response.
- Services own transactions, authorization, quiz lifecycle, scoring, retry safety, and coordination across models.
- Domain classes hold definitions, validation rules, policies, and state types without HTTP concerns.
- Models handle persistence and never accept ownership from request data.
- Filters apply session authentication, CSRF, bearer-token checks, and throttling.
- Commands reconcile expired assessments and clean up temporary/orphaned data in bounded batches.

Use explicit routes with automatic routing disabled. Register fixed routes before the final public `/{username}` route.

## Frontend

Server-rendered PHP is the default. Pages share accessible layouts, navigation, flash messages, form errors, and responsive CSS.

- Teacher dashboard, quiz library, and later reports use server-rendered views with small plain-JavaScript enhancements.
- The quiz builder uses the pinned local Vue 3.5.43 global production build without single-file components, Vite, or an SPA router.
- Student assessment and practice pages use plain JavaScript modules and server APIs.
- Render authored content as escaped plain text. Do not send answer keys or private media paths before policy allows them.
- Support keyboard use, visible focus, responsive layouts, clear save/retry states, and screen-reader-friendly status updates.

The teacher workspace keeps its light-theme design system in `public/assets/css/teacher.css`: color and typography tokens, a curated reset, accessibility utilities, and reusable button, card, form, badge, alert, dialog, table, tab, menu, empty-state, and toast components. Teacher views compose those primitives with page-specific layout classes. The landing, authentication, public quiz, and framework error pages retain their independent styling until a later shared-design migration.

Browser state is limited by mode:

- Assessments store the bearer credential in same-tab `sessionStorage`; the server remains authoritative for answers, progress, and deadlines.
- Practice stores its progress and review data in the current tab only; clearing tab storage loses the session.

### Implemented teacher authoring

Successful login and registration lead to `/quizzes`; `/dashboard` remains the overview. Authenticated teacher pages are server-rendered at `/dashboard`, `/quizzes`, `/quizzes/archived`, `/quizzes/trash`, and `/quizzes/{publicId}/edit`. They share a responsive teacher layout with overview/library navigation, a deliberately unavailable Results item, profile context, and a CSRF-protected logout form.

The dashboard queries quiz totals, published quizzes, finalized assessment submissions, practice starts, and recently changed quizzes. The library supports title search, status/mode filters, sorting, pagination, and separate active, archived, and trash views. Empty states and metrics always come from stored data.

The builder uses the locally vendored Vue 3.5.43 global production build and same-origin JSON endpoints under `/api/v1`. It edits every schema-backed quiz setting and supports single choice, multi-select, short text, and a true/false single-choice preset. Teacher preview renders the current document without grading, starting an attempt, or exposing it publicly.

The builder saves one versioned quiz document containing settings, questions, options, accepted text answers, and safe media descriptors. Changes autosave one second after the last edit and may also be flushed explicitly; invalid documents enter a visible validation-error state instead of being persisted. Mutations are serialized so regenerated CSRF tokens remain ordered, and navigation warns while changes are pending. The service locks the owned quiz, compares its version, validates the complete aggregate and nested ownership, and writes quiz, question, and option changes in one transaction. A version mismatch returns `409` and pauses saving until the teacher reloads the server document or explicitly overwrites it.

Passcodes use explicit `unchanged`, `set`, and `clear` operations. Only the configured/not-configured state is returned to the client; a passcode value or hash is never returned. IDs, share tokens, and option codes are generated on the server.

Lifecycle changes are service-owned: drafts publish only after full content validation; published quizzes close and closed quizzes reopen; any active quiz may be archived. Unarchiving returns an unfrozen quiz to draft and a frozen quiz to closed. Published quizzes must first be closed or archived before soft deletion, and restoration preserves the stored lifecycle status. Duplication creates an unfrozen, unlisted draft with new identifiers, zero activity, and independent media copies.

The future student-start transaction will set `frozen_at` on the first real start. Once frozen, mode, authored text, timers, questions, answers, explanations, points, and media cannot change. Status, listing, schedule, admission fields, feedback/visibility policy, shuffling, and integrity policy remain editable for future starts.

Published and closed quizzes have a stable `/q/{shareToken}` information page. Draft, archived, deleted, and unknown tokens return 404. Until the student runner milestone, the page exposes only safe quiz metadata, teacher display name, availability/passcode indicators, and a notice that taking the quiz is unavailable; it never exposes questions, answers, hashes, or private paths.

## Identity and authorization

EduTest owns email/password registration, login, logout, and credential storage. Persistent account, profile, password-hash, and reserved recovery fields live in the single `users` table. The session stores only the authenticated user ID; CI4 file/cache storage holds temporary sessions, CSRF state, and rate-limit buckets.

Passwords use PHP's `PASSWORD_DEFAULT` API and are rehashed when needed. Registration starts a session immediately. Email verification and password recovery are deferred; recovery database fields are reserved but have no routes or UI. Every account is a teacher in the MVP, so there are no role, group, identity, or permission tables.

Teachers directly own quizzes. Authorize nested resources through the complete ownership chain. Public IDs, share tokens, question IDs, option codes, attempt tokens, and signed media URLs are identifiers or credentials, not proof of teacher ownership.

Teacher mutations use session authentication and CSRF protection. Student assessments use a random bearer credential stored as a hash on the server. Practice uses short-lived signed tickets and does not create an account or individual result record.

## Quiz lifecycle

Quizzes move through draft, published, closed, and archived states and may be soft-deleted. Drafts do not accept students.

The first real assessment or practice start freezes authored content for that quiz revision: questions, options, answer keys, points, explanations, timers, and media references. Teachers duplicate a frozen quiz to change that content. Availability and selected policies may change for future starts without rewriting existing sessions.

## Assessment flow

An assessment start validates availability, optional passcode, identity fields, and quiz definition in one transaction. Each deliberate start creates an independent attempt; retrying the same start request returns the existing attempt.

The server controls question order, answer drafts, locking, scoring, and deadlines. A student cannot return to a locked question. Save versions, submit keys, attempt versions, row locks, and deterministic retry handling prevent duplicate writes or progression.

The effective deadline is the earliest applicable question timer, total timer, and captured closing time. Database UTC time is authoritative. Finalized results may be viewed only by the quiz owner.

## Practice flow

Practice is anonymous and non-authoritative. It persists no identity, IP address, browser agent, answer, score, or individual result in the database, server session, cache, or application logs.

The server issues a short-lived signed ticket, sends one sanitized question at a time, and grades submissions without persistence. The browser keeps progress and encrypted review receipts in same-tab storage. Teachers see only the aggregate number of deduplicated starts.

## Scoring

Use one PHP scoring engine for assessment and practice:

- Single choice: full points or zero.
- Multi-select: reward correct selections, subtract incorrect selections proportionally, and clamp the result from zero to full points.
- Short text: compare validated normalized text using the rules in the product plan.

Use decimal arithmetic and round each question award half-up to two decimal places before totaling. Never use binary floating-point for stored scores.

## Media

Questions and options may reference one image, audio file, or supported external video. Uploaded files remain outside `public/` under random paths.

Validate file size, decoded content/MIME, and ownership. Re-encode images where required, reject unsafe formats, and serve private files only through short-lived signed URLs. A stored private path must never appear in a client response.

The authoring implementation stores random quiz media below `writable/uploads/quiz-media`. JPEG, PNG, and WebP images up to 5 MiB are decoded and re-encoded to strip metadata; supported audio up to 20 MiB is content-validated and stored with a server-selected extension. YouTube, Vimeo, and direct HTTPS MP4 URLs are normalized rather than accepted as arbitrary embed markup. Question and option media mutations require a persisted owned target, follow the complete ownership chain, compare the current quiz version, and increment it transactionally.

Failed media writes remove newly created files. Replaced or deleted files are cleaned after the database commit, while soft-deleted quiz media is retained for restoration. Duplicated quizzes receive physically independent copies of uploaded media.

Private images and audio are delivered through expiring HMAC-signed `/media/{token}` URLs with range support and `nosniff`/private-cache headers. Signing uses the untracked `encryption.key` environment value; database responses contain a temporary playback URL, never `media_src`.

## API and errors

The implemented authoring API provides CSRF recovery; quiz create/load/save; publish, close, reopen, archive, unarchive, trash, restore, and duplicate actions; and question/option media replacement and removal. All routes require the teacher session and mutation routes remain CSRF-protected.

Responses use `{data, meta}` and `{error, meta}` envelopes with camelCase fields. Timestamps are UTC ISO strings, decimals are exact strings, and numeric database IDs are serialized as strings for JavaScript safety. Authoring services derive ownership only from the authenticated session and ignore submitted ownership identifiers.

Return appropriate 4xx errors for validation, authentication, authorization, missing resources, expired credentials, and version conflicts. Do not expose stack traces, SQL, secrets, or internal filesystem paths.

## Operations

Local development uses MAMP and phpMyAdmin. Production targets cPanel/shared hosting with TLS, SMTP, cron, backups, and `public/` as the preferred document root.

Keep secrets in `.env`, writable data private, production errors hidden, and ordinary logs free of credentials, tokens, signed URLs, and practice bodies. Back up the database and media before applying SQL changes and test restore procedures before launch.

Authoring deployments require an untracked `encryption.key`. To support the documented 20 MiB audio limit, the PHP web runtime must set `upload_max_filesize` above 20 MiB and `post_max_size` above that value; the builder reports a configuration warning when the effective runtime limits are lower.
