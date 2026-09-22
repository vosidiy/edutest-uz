# Changelog

## [Unreleased]

### Added

- Canonical application schema in `docs/schema.sql`.
- Consolidated CodeIgniter 4 guidance in `docs/SKILL.md`.
- Project-owned session authentication, teacher registration, and schema-backed application models.
- Authenticated teacher dashboard and searchable quiz library with real empty states and aggregate metrics.
- Vue-powered quiz builder with hybrid autosave, optimistic conflicts, preview, all schema-backed settings, question types, and answer editing.
- Quiz publishing, close/reopen, archive/restore, soft-delete/restore, duplication, and frozen-content enforcement.
- Private question/option image and audio storage, validated external video URLs, and short-lived signed media delivery.
- Stable public quiz-information pages for published and closed quizzes.

### Changed

- Simplified `AGENTS.md`, `docs/PLAN.MD`, and `docs/ARCHITECTURE.MD`.
- Made the architecture focus on frontend/backend behavior and the plan focus on product vision.
- Corrected the CI4 transaction example and restored exact multi-select and short-text scoring rules.
- Clarified documentation authority and removed a Shield-specific filter from the generic CI4 route example.
- Replaced Shield with a single-table email/password authentication design and made `schema.sql` a complete ready-to-import schema.
- Added optional registration phone numbers, authentication throttling, secure password hashing/rehashing, and reserved recovery fields.
- Redirected authenticated registration and login flows to the quiz library while retaining the public landing page at `/`.
- Updated authentication throttling and validation integration for CodeIgniter 4.7 compatibility.
- Pinned and self-hosted the Vue 3.5.43 production global build for the builder.

### Removed

- Duplicated schema definitions from the architecture.
- Nested skill references, deployment notes, README, and prototype documentation assets.
- Shield, its package-owned tables, and the database migration workflow.

## [1.0.0] — 2026-09-22

- Initial project scaffolding and planning.
