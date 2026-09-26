# Changelog

## [Unreleased]

### Added

- Practice-only “Try again” on completed results, with fresh admission/settings/timers, configured shuffling, deduplicated retry, and previous-result retention on failure.
- Optional cover selection and preview in quiz creation, with upload retry on the same saved draft and continuation without a cover.

- Responsive student introduction, assessment admission, one-question player, timers, instant client feedback, and student results for assessment and anonymous practice.
- Signed admission/recovery credentials, bearer-protected assessment APIs, transactional first-start freezing, captured policies and shuffle order, and idempotent ordered answer synchronization.
- Offline-friendly assessment outbox, provisional/server-confirmed scores, explicit conflict recovery, late-sync flags, and opt-in informational integrity events.
- Anonymous local-only practice grading and review, expiring start deduplication, and signed media renewal without individual results or PHP sessions.
- Optional private quiz covers with builder controls, replacement/removal, independent duplication, and frozen-content enforcement.
- Shared exact-arithmetic PHP/JavaScript scoring fixtures and isolated student service, feature, media, and browser-state tests.
- Canonical schema support for `quizzes.cover_src` and `attempts.late_sync`; the developer reported applying the corresponding incremental SQL.

- Canonical application schema in `docs/schema.sql`.
- Consolidated CodeIgniter 4 guidance in `docs/SKILL.md`.
- Project-owned session authentication, teacher registration, and schema-backed application models.
- Shared responsive teacher layout with overview/library navigation, profile context, CSRF-protected logout, and a disabled future Results area.
- Authenticated dashboard and searchable quiz library with real aggregate metrics, sorting, filtering, pagination, active/archive/trash views, contextual lifecycle actions, and honest empty states.
- Vue-powered three-pane quiz builder with one-second autosave, immediate manual saves, serialized mutations, CSRF recovery, retry/validation states, unsaved-change warnings, and reload-or-overwrite conflict handling.
- Complete quiz document editing for schema-backed settings, accessible question/option reordering, single choice, multi-select, short text, true/false presets, accepted answers, explanations, points, timers, and teacher-only preview.
- Transactional aggregate validation and saving with optimistic versions, nested ownership checks, server-generated public identifiers/share tokens/option codes, and explicit passcode operations that never return passcodes or hashes.
- Quiz publishing, close/reopen, archive/unarchive, soft-delete/restore, duplication with independent media, publication-readiness validation, and frozen-content enforcement.
- Private question/option image and audio storage, content-derived validation, image metadata stripping, validated external video URLs, replacement cleanup, and short-lived signed media delivery with range support.
- Stable public quiz-information pages for published and closed quizzes with safe metadata and availability messaging.
- Isolated authoring and route tests covering ownership, validation, conflicts, lifecycle transitions, practice constraints, frozen policies, private media, unsafe uploads/URLs, signed credentials, and unauthenticated access.
- Teacher-scoped CSS design-system primitives for buttons, cards, forms, badges, alerts, dialogs, tables, tabs, menus, empty states, and accessibility helpers.

### Changed

- Made quiz-creation Cancel/× buttons submit a separate native dialog-dismissal form, independent of title validation and JavaScript close listeners. Added file-versioned teacher script URLs to avoid stale browser code after updates.

- Fixed creation-dialog Cancel and close buttons to bypass required-title validation, preserve Escape/focus behavior, and clean temporary cover previews. Save/upload operations expose progress and temporarily prevent dismissal.

- Activated the public quiz introduction and student player while keeping teacher reports/CSV deferred.
- Made student introduction, player, and results styling self-contained in `player.css`, removing the `teacher.css` dependency while preserving the indigo appearance, components, and accessibility styles. Teacher, landing, and authentication styles remain unchanged.
- Replaced server-only timing and practice server-grading assumptions with deliberately inspectable client-loaded keys and browser-enforced offline timing.

- Simplified `AGENTS.md`, `docs/PLAN.MD`, and `docs/ARCHITECTURE.MD`.
- Made the architecture focus on frontend/backend behavior and the plan focus on product vision.
- Corrected the CI4 transaction example and restored exact multi-select and short-text scoring rules.
- Clarified documentation authority and removed a Shield-specific filter from the generic CI4 route example.
- Replaced Shield with a single-table email/password authentication design and made `schema.sql` a complete ready-to-import schema.
- Added optional registration phone numbers, authentication throttling, secure password hashing/rehashing, and reserved recovery fields.
- Redirected authenticated registration and login flows to the quiz library while retaining the public landing page at `/`.
- Updated the landing page to send authenticated teachers directly to their quiz library.
- Updated authentication throttling and validation integration for CodeIgniter 4.7 compatibility.
- Pinned and self-hosted the Vue 3.5.43 production global build for the builder.
- Refactored the teacher dashboard, quiz library, builder, and dialogs onto a tokenized indigo light theme with a curated reset and composable component classes.

### Security

- Kept teacher CSRF protection while isolating stateless student authorization and excluding student/media traffic from debug-toolbar and page-cache persistence.
- Rechecked frozen/status/version conditions under the quiz lock after media processing to reject first-start/upload races and clean failed uploads.
- Validated assessment progress against owned frozen questions and persisted locks; ignored client-reported scores and ownership identifiers.
- Rejected invalid calendar timestamps and out-of-range versions, added bounded MySQL contention retries, and prevented native admission forms from leaking identity through URL query strings before JavaScript loads.

- Required quiz ownership to come exclusively from the authenticated session across authoring, lifecycle, duplication, and media operations.
- Kept uploaded media outside `public/`, prevented private-path disclosure, and failed signed delivery closed when `encryption.key` is unavailable.
- Preserved CSRF protection for all session-authenticated mutations and serialized rotating-token requests in the browser.

### Documentation

- Recorded the implemented teacher routes, versioned document contract, lifecycle/frozen behavior, private-media rules, and deployment upload limits in `docs/ARCHITECTURE.MD`.
- Deferred public teacher profiles beyond the authoring milestone in `docs/PLAN.MD` to match the current product scope.

### Removed

- Removed the one-time student-player upgrade script after the developer reported applying it; the canonical schema remains unchanged.

- Duplicated schema definitions from the architecture.
- Nested skill references, deployment notes, README, and prototype documentation assets.
- Shield, its package-owned tables, and the database migration workflow.

## [1.0.0] — 2026-09-22

- Initial project scaffolding and planning.
