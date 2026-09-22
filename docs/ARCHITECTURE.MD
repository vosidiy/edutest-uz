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

- Teacher dashboard and reports use server-rendered views with small plain-JavaScript enhancements.
- The quiz builder may use a pinned local Vue 3 production build without single-file components, Vite, or an SPA router.
- Student assessment and practice pages use plain JavaScript modules and server APIs.
- Render authored content as escaped plain text. Do not send answer keys or private media paths before policy allows them.
- Support keyboard use, visible focus, responsive layouts, clear save/retry states, and screen-reader-friendly status updates.

Browser state is limited by mode:

- Assessments store the bearer credential in same-tab `sessionStorage`; the server remains authoritative for answers, progress, and deadlines.
- Practice stores its progress and review data in the current tab only; clearing tab storage loses the session.

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

## API and errors

Use same-origin JSON under `/api/v1` for interactive operations. Responses use consistent success/error envelopes and UTC timestamps. Serialize large numeric IDs as strings for JavaScript safety.

Return appropriate 4xx errors for validation, authentication, authorization, missing resources, expired credentials, and version conflicts. Do not expose stack traces, SQL, secrets, or internal filesystem paths.

## Operations

Local development uses MAMP and phpMyAdmin. Production targets cPanel/shared hosting with TLS, SMTP, cron, backups, and `public/` as the preferred document root.

Keep secrets in `.env`, writable data private, production errors hidden, and ordinary logs free of credentials, tokens, signed URLs, and practice bodies. Back up the database and media before applying SQL changes and test restore procedures before launch.
