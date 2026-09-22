# EduTest agent guide

EduTest is a CodeIgniter 4 quiz platform in early development. Keep changes aligned with the documented product plan, architecture, and database schema.

## Read before changing the project

- `docs/PLAN.MD` — product vision, MVP scope, defaults, and milestones.
- `docs/ARCHITECTURE.MD` — frontend/backend design and runtime behavior.
- `docs/schema.sql` — the only source of truth for database structure.
- `docs/SKILL.md` — general CodeIgniter 4 implementation guidance.
- `docs/CHANGELOG.md` — notable changes (always need to update whenever new changes made).

The user's current request governs the requested change. Otherwise, use each document for its own domain:

- `docs/PLAN.MD` controls product intent and scope.
- `docs/ARCHITECTURE.MD` controls application behavior and technical design.
- `docs/schema.sql` controls exact database DDL.
- `docs/SKILL.md` controls general CodeIgniter 4 conventions.

Resolve cross-document contradictions in the same change instead of silently choosing one source.

## Technology

- PHP 8.4 and a compatible, Composer-managed CodeIgniter 4 release.
- Project-owned email/password authentication using CI4 sessions.
- MySQL 8.4 with InnoDB and `utf8mb4`.
- Server-rendered views, plain JavaScript, and plain CSS.
- A pinned local Vue 3 production build only for the quiz builder; no Node/Vite runtime or SPA router.
- MAMP and phpMyAdmin locally; cPanel/shared hosting in production.
- `public/` is the web root. Runtime files and private uploads belong under `writable/` or another private configured path.

## Application boundaries

- Controllers handle HTTP input/output and call services.
- Services own business rules, transactions, scoring, and state changes.
- Models provide narrow persistence; define safe `$allowedFields`.
- Filters handle authentication, CSRF, bearer credentials, and throttling.
- Views escape untrusted output and contain no business logic.
- Obtain teacher ownership from `service('auth')->id()`, never submitted user IDs.
- Use explicit, verb-specific routes with automatic routing disabled.

Preserve the privacy distinction between quiz modes:

- Assessment mode stores attempts, answers, results, timing, and disclosed identity information.
- Practice mode stores no individual identity, answers, or results; only an aggregate start count and short-lived deduplication data are persisted.

## Configuration and secrets

Keep environment-specific values and secrets in an untracked root `.env`. Never commit credentials, encryption/signing keys, tokens, or production connection details to PHP configuration, documentation, fixtures, or logs.

Use `CI_ENVIRONMENT = development` locally and `production` on the production host. Do not expose stack traces, debug tools, `phpinfo()`, or environment dumps in production.

## Database changes

`docs/schema.sql` is the canonical, complete fresh-install schema. The project does not use database migrations; the developer applies SQL manually through phpMyAdmin.

For every schema change:

1. Update `docs/schema.sql`.
2. Give the user exact incremental MySQL SQL to run in phpMyAdmin against an existing database.
3. Update the architecture when behavior or ownership changes.
4. Update `docs/CHANGELOG.md`.

Do not alter the user's database unless explicitly asked. Import `docs/schema.sql` only into an empty database; it is not an upgrade script.

## Working rules

- Respect the requested scope and preserve unrelated user changes.
- Do not treat planned features as implemented features.
- Do not edit `vendor/`.
- Use Query Builder, models, or bound SQL; never concatenate untrusted SQL.
- Keep session-authenticated mutations CSRF-protected.
- Escape output in context and keep authored quiz content as plain text.
- Keep private media outside `public/`; validate uploads by actual content.
- Update `docs/CHANGELOG.md` for notable product, architecture, schema, security, or operational changes.

## Verification

Run checks appropriate to the change and report only what actually ran:

- Focused PHPUnit tests, then relevant broader tests.
- A separate test database, never the MAMP development database.
- `php spark routes` and `php spark filter:check` for route/filter changes.
- Fresh import and schema comparison in a separate MySQL test database for database changes.
- Owner/non-owner access, validation failures, retries, deadlines, and privacy-negative cases where relevant.

If required tooling is unavailable, state what was not verified and how the user can verify it.
