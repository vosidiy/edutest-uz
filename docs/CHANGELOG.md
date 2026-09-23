# Changelog

## [Unreleased]

### Added

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
- Stable public quiz-information pages for published and closed quizzes with safe metadata and pre-student-runner availability messaging.
- Isolated authoring and route tests covering ownership, validation, conflicts, lifecycle transitions, practice constraints, frozen policies, private media, unsafe uploads/URLs, signed credentials, and unauthenticated access.
- Teacher-scoped CSS design-system primitives for buttons, cards, forms, badges, alerts, dialogs, tables, tabs, menus, empty states, and accessibility helpers.

### Changed

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

- Required quiz ownership to come exclusively from the authenticated session across authoring, lifecycle, duplication, and media operations.
- Kept uploaded media outside `public/`, prevented private-path disclosure, and failed signed delivery closed when `encryption.key` is unavailable.
- Preserved CSRF protection for all session-authenticated mutations and serialized rotating-token requests in the browser.

### Documentation

- Recorded the implemented teacher routes, versioned document contract, lifecycle/frozen behavior, private-media rules, and deployment upload limits in `docs/ARCHITECTURE.MD`.
- Deferred public teacher profiles beyond the authoring milestone in `docs/PLAN.MD` to match the current product scope.

### Removed

- Duplicated schema definitions from the architecture.
- Nested skill references, deployment notes, README, and prototype documentation assets.
- Shield, its package-owned tables, and the database migration workflow.

## [1.0.0] — 2026-09-22

- Initial project scaffolding and planning.
