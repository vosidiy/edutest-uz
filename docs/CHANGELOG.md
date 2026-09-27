# Changelog

## [Unreleased]

### Added

- Viewport-height quiz builder with independent desktop panel scrolling and state-preserving Questions/Editor/Settings switching on tablet/mobile.
- Native Quiz details dialog for staged title/cover edits, cancellable local previews, focus restoration, and retained-cover upload retries.
- Clear Saved / Save changes / Saving / Resolve conflict controls, serialized aggregate/cover saves, and acknowledgement merging that preserves edits made during requests.
- Editor-local notices and a collapsed answers/timers disclosure, plus isolated save-flow and Chrome regression coverage for scrolling, dialog behavior, and in-flight edits.

- Unified centered teacher dashboard with account/logout disclosure, six workspace metrics, and one enriched quiz library covering active, archived, deleted, and historical assessment activity.
- Shared sticky quiz header across Builder, Responses, and attempt review, with contextual actions and archived/deleted restoration guidance.
- Isolated dashboard/header/redirect regression tests and actual-view Chrome checks at desktop, tablet, and mobile widths.

- Added responsive Builder/Responses navigation to the quiz-builder header for Assessment quizzes and Practice quizzes with retained assessment history.

- Nine-digit share codes for newly created and duplicated quizzes, with collision retries and permanent compatibility for existing 64-character links.
- Non-blocking fullscreen controls and status messaging on monitored assessment introductions and active player pages.
- Manual `short-codes-integrity-upgrade.sql` for compatible share-token storage and the reduced integrity-event constraint.

- Versioned, compact `attempts.responses` JSON with shared serialization/validation for assessment synchronization and teacher review.
- Manual destructive `attempt-responses-upgrade.sql`; it clears disposable assessments and integrity events while preserving authored content, papers/media, practice data, and first-start metadata.
- Isolated MySQL upgrade/concurrency verification and repeatable 20/100/500-question storage/write benchmarks.

- Immutable shared quiz papers captured per used revision, with assessment and practice runs bound to their exact start definition.
- Canonical `quiz_papers`, paper foreign keys, historical attempt question identifiers, and `first_started_at`.
- Updated fresh-install schema with equal-weight questions and image-only answer choices.

- Practice-only “Try again” on completed results, with fresh admission/settings/timers, configured shuffling, deduplicated retry, and previous-result retention on failure.
- Optional cover selection and preview in quiz creation, with upload retry on the same saved draft and continuation without a cover.

- Responsive student introduction, assessment admission, one-question player, timers, instant client feedback, and student results for assessment and anonymous practice.
- Signed admission/recovery credentials, bearer-protected assessment APIs, transactional paper capture at first use, captured policies and shuffle order, and idempotent ordered answer synchronization.
- Offline-friendly assessment outbox, provisional/server-confirmed scores, explicit conflict recovery, late-sync flags, and opt-in informational integrity events.
- Anonymous local-only practice grading and review, expiring start deduplication, and signed media renewal without individual results or PHP sessions.
- Optional private quiz covers with builder controls, replacement/removal, independent duplication, and historical paper retention.
- Shared exact-arithmetic PHP/JavaScript scoring fixtures and isolated student service, feature, media, and browser-state tests.
- Canonical schema support for `quizzes.cover_src` and `attempts.late_sync`; the developer reported applying the corresponding incremental SQL.
- Protected teacher Results workspace with historical assessment summaries, per-quiz summaries, filtered attempts, complete owner-only attempt review, integrity timelines, and formula-safe filtered CSV exports.
- Responsive reporting components for metrics, attempt tables, answer review, media, empty states, and integrity timelines.

- Canonical application schema in `docs/schema.sql`.
- Consolidated CodeIgniter 4 guidance in `docs/SKILL.md`.
- Project-owned session authentication, teacher registration, and schema-backed application models.
- Shared responsive teacher layout with overview/library/Results navigation, profile context, and CSRF-protected logout.
- Authenticated dashboard and searchable quiz library with real aggregate metrics, sorting, filtering, pagination, active/archive/trash views, contextual lifecycle actions, and honest empty states.
- Vue-powered three-pane quiz builder with one-second draft autosave, explicit non-draft saves, serialized mutations, CSRF recovery, retry/validation states, unsaved-change warnings, and reload-or-overwrite conflict handling.
- Complete quiz document editing for schema-backed settings, accessible question/option reordering, single choice, multi-select, short text, accepted answers, explanations, equal-weight scoring, an optional total timer, and teacher-only preview.
- Transactional aggregate validation and saving with optimistic versions, nested ownership checks, server-generated public identifiers/share tokens/option codes, and explicit passcode operations that never return passcodes or hashes.
- Quiz publishing, close/reopen, archive/unarchive, soft-delete/restore, duplication with independent media, publication-readiness validation, and editable post-start revisions.
- Private question image/audio/video and answer-option image storage, content-derived validation, image metadata stripping, validated external video URLs, replacement cleanup, and short-lived signed media delivery with range support.
- Stable public quiz-information pages for published and closed quizzes with safe metadata and availability messaging.
- Isolated authoring and route tests covering ownership, validation, conflicts, lifecycle transitions, practice constraints, paper revision policies, private media, unsafe uploads/URLs, signed credentials, and unauthenticated access.
- Teacher-scoped CSS design-system primitives for buttons, cards, forms, badges, alerts, dialogs, tables, tabs, menus, empty states, and accessibility helpers.

### Changed

- Redirected teacher login/registration and authenticated landing navigation to `/dashboard`; preserved old library and Results-overview URLs as authenticated, filter-aware redirects.
- Added dashboard report-eligibility filtering and assessment-summary sorting using grouped, owner-scoped queries without loading answer documents.
- Kept Back and manual Save controls accessible on narrow builder layouts; versioned teacher CSS URLs to avoid stale layout caches.

- Made quiz mode editable after any number of starts. Saved mode changes apply only to future starts; existing Assessment attempts and Practice runs retain their immutable paper mode.
- Kept historical assessment reports visible after a quiz switches to Practice, labeled current and received modes separately, and exposed both assessment submissions and Practice starts in teacher quiz activity summaries.
- Changed unused-paper cleanup to depend on attempt/Practice-key references rather than the live quiz mode.

- Limited one-second builder autosave to drafts; published, closed, and archived quizzes now require explicit saves, and incomplete drafts expose a disabled Publish action with readiness guidance.
- Reduced integrity monitoring to hidden-tab and genuine fullscreen-exit signals, removing focus, return, and inactivity reports.
- Renamed no-passcode student access from a private link to a share link because short quiz codes are locators rather than secrets.

- Consolidated answer persistence into one atomic attempt-row update per changed sync batch, retaining public player contracts, exact scoring, retries, offline recovery, and immutable finalized results.
- Moved question-order/answer review to response JSON plus shared immutable papers; kept report/CSV summaries and authorization/event checks independent of answer-document reads.

- Standardized every question at `1.00` maximum credit while retaining proportional multi-select partial credit; results now use equivalent correct-question counts and percentages rather than points.
- Changed the builder to one Add question action, immediate answer-type resets, semantic radio/checkbox correctness controls, and lettered choices across preview, player feedback, and historical review.
- Added a file-version query to the builder application script so updated templates cannot reuse stale cached JavaScript and fail while rendering lettered choices.
- Changed total timer authoring to exact `timeLimitMinutes` values in half-minute steps from `0.5` to `1440`, while keeping whole-second runtime storage.
- Bumped immutable papers to schema version 2 and changed attempt items from weighted `points` and question deadlines to bounded internal `credit`.
- Restricted answer-option media to JPEG/PNG/WebP images while retaining image/audio/validated-video media for questions.
- Replaced permanent content freezing with freely editable live quizzes backed by immutable papers; published edits must remain publication-ready.
- Moved assessment load, synchronization, official grading, student results, media renewal, and teacher attempt review from live rows to the bound immutable paper.
- Simplified teacher Results to finalized/in-progress counts, finalized average scores, filtered attempt lists, paper revisions, detailed attempt review, integrity timelines, and CSV export.
- Made media cleanup paper-reference-aware so historical result media remains available after live replacement or deletion.

- Made quiz-creation Cancel/× buttons submit a separate native dialog-dismissal form, independent of title validation and JavaScript close listeners. Added file-versioned teacher script URLs to avoid stale browser code after updates.

- Fixed creation-dialog Cancel and close buttons to bypass required-title validation, preserve Escape/focus behavior, and clean temporary cover previews. Save/upload operations expose progress and temporarily prevent dismissal.

- Activated the public quiz introduction, student player, and teacher assessment reporting workspace.
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
- Rechecked status/version conditions under the quiz lock after media processing to serialize start/upload races and clean failed uploads.
- Validated assessment progress against owned paper questions and persisted locks; ignored client-reported scores and ownership identifiers.
- Rejected invalid calendar timestamps and out-of-range versions, added bounded MySQL contention retries, and prevented native admission forms from leaking identity through URL query strings before JavaScript loads.

- Required quiz ownership to come exclusively from the authenticated session across authoring, lifecycle, duplication, and media operations.
- Kept uploaded media outside `public/`, prevented private-path disclosure, and failed signed delivery closed when `encryption.key` is unavailable.
- Preserved CSRF protection for all session-authenticated mutations and serialized rotating-token requests in the browser.
- Derived every report query from the authenticated owner, returned indistinguishable 404 responses for malformed/non-owned identifiers, and restricted IP/browser data to attempt detail while excluding credentials, private paths, answers, and connection details from CSV.

### Documentation

- Recorded the implemented teacher routes, versioned document contract, lifecycle/paper-revision behavior, private-media rules, and deployment upload limits in `docs/ARCHITECTURE.MD`.
- Deferred public teacher profiles beyond the authoring milestone in `docs/PLAN.MD` to match the current product scope.
- Recorded paper-backed historical result visibility, basic finalized totals, timezone filtering, attempt-detail privacy, and CSV boundaries.

### Removed

- Teacher sidebar/mobile bottom navigation and separate quiz-library/Results-overview views; their features now live on the dashboard.

- The `attempt_items` table and model; no production service queries per-answer database rows.

- Removed score distributions, question-accuracy/timing aggregates, hardest/slowest insights, workspace integrity totals, and permanent frozen-content builder controls.
- Removed configurable question points, per-question timers/deadlines, `question_timeout`, the true/false quick-add preset, and audio/video media from answer options.

- Removed the one-time student-player upgrade script after the developer reported applying it; the canonical schema remains unchanged.
- Removed the one-time quiz-simplification upgrade script after the developer reported applying it; `schema.sql` remains the canonical current structure.

- Duplicated schema definitions from the architecture.
- Nested skill references, deployment notes, README, and prototype documentation assets.
- Shield, its package-owned tables, and the database migration workflow.

## [1.0.0] — 2026-09-22

- Initial project scaffolding and planning.
