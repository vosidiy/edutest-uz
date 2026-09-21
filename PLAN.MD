# EduTest — product and delivery plan

Status: agreed implementation specification. This document describes planned behavior, not an already implemented application. See [ARCHITECTURE.MD](ARCHITECTURE.MD) for schema, HTTP contracts, state transitions, and deployment.

## 1. Purpose and boundaries

Build an education-focused quiz SaaS for independent teachers, preparation centers acting through a teacher account, and recruiters. A teacher creates multimedia quizzes, shares a link, and either collects student assessments or offers anonymous practice.

Start with one CodeIgniter 4 application, PHP 8.4, MySQL 8.4, Shield authentication, server-rendered pages, plain JavaScript, and locally hosted Vue 3 for the quiz builder. There are no frontend build tools, SPA router, organization accounts, workspace tables, or team memberships. A user owns quizzes directly; a question or option stores its own media reference.

The repository currently contains a static `PROTOTYPE-UI` reference. This documentation milestone does not scaffold the application, create migrations, or change the prototype.

### Quiz modes

| Behavior | Assessment | Practice |
| --- | --- | --- |
| Purpose | Teacher evaluates students | Visitor tests their knowledge |
| Identity | Required name; configurable email and phone | Anonymous |
| Persist answers/results | Yes | Never |
| Creator sees | Individual results and analytics | Aggregate started-attempt count only |
| Grading | Server-side | Server-side for each answer |
| Progress/timers | Server-authoritative | Browser-managed learning aids |
| Refresh recovery | Persisted state and attempt token | Same tab's `sessionStorage` |
| Public teacher-page listing | Optional | Optional |

Opaque choice codes identify answers; correct-answer mappings remain on the server. Codes are not encryption and do not make client-side grading confidential. Server authority applies to assessment results; practice produces no authoritative result record.

## 2. Teacher accounts and public profiles

Teachers register with email/password, verify their email in production, sign in/out, and reset forgotten passwords. Use Shield rather than implementing password storage. Extend Shield's `users` record with display name, plain-text biography, timezone, and public-page switch; do not add a separate profile table.

The public URL is `https://edutest.com/{username}`. Use Shield's username as the single canonical handle: lowercase ASCII letters, digits, and underscores, 3–30 characters, unique. Reserve application route names. Teachers can change their username; the old URL stops resolving and redirects are deferred.

Public pages are disabled by default. Enabling one never automatically lists quizzes. A public page displays only the teacher's display name, biography, and explicitly listed, published, currently available quizzes. Do not expose the account email or private result data. Unknown/disabled profiles return 404.

Both modes may be listed. Each quiz card and start screen clearly explains:

- **Assessment:** results are saved and shared with the teacher.
- **Practice:** answers and results are not saved on the server; grading requires an internet connection.

Quizzes not listed remain accessible by their unguessable share links. Listing does not bypass assessment passcodes or identity requirements. No global teacher directory, platform search, follower system, or public result leaderboard in the MVP.

## 3. Authoring, publication, and sharing

Teachers create, edit, duplicate, publish, manually close, archive, and soft-delete quizzes. Drafts cannot accept students. Closed quizzes can be republished; archived quizzes can be restored to closed. Soft-deleted quizzes disappear from normal pages and public access. Existing sessions follow the continuity rules in section 5.

Configure title, description, instructions, quiz mode, optional total timer, opening/closing dates, randomization, feedback, and profile listing. Sharing provides a copyable `/q/{share_token}` link and a QR code generated locally in the browser.

Assessment settings additionally include an optional passcode, independently hidden/optional/required email and phone fields, and integrity monitoring. Name is always required. Email and phone are self-reported and unverified. Every explicit start creates an independent attempt, even when its identity fields match an earlier attempt; a lost-response retry resumes the attempt created by that start request.

Practice is anonymous and unlimited, with no identity form, passcode, or integrity monitoring. Its local total/per-question timers are learning aids. It still supports opening/closing dates for new starts.

### Questions and media

Questions belong directly to a quiz; no question bank yet. Support single choice, multi-select, and short text. Each question has positive points (default 1), an optional explanation, and an optional timer. True/false is a single-choice preset with two options. Ordering, fill-blank, and a distinct true/false type are deferred.

Question prompts and answer options may contain text, one media attachment, or both. At least one is required. Each option receives a stable, server-generated opaque code; its correctness flag remains server-only until the feedback policy permits disclosure. Short-text questions store 1–20 accepted strings as a validated JSON array on the question. PHP normalizes and deduplicates them for validation and scoring without storing a second normalized copy.

| Media | MVP support |
| --- | --- |
| Images | JPEG, PNG, WebP; at most 5 MiB per file |
| Audio | MP3, M4A, OGG, WAV; at most 20 MiB per file |
| Video | Validated YouTube/Vimeo links or direct HTTPS MP4 URLs |
| Uploaded video / arbitrary embeds | Not supported |

Keep authored content as plain text and render it safely. Images can represent mathematical formulas. Specialist IELTS sections, shared reading passages, controlled playback, essays, speaking responses, and mathematical typesetting are deferred.

### Content stability

The first real assessment or practice start freezes mode, questions, option codes, answer keys, points, explanations, media references, and question timers. Referenced media bytes are immutable. Teachers duplicate a frozen quiz to change content. Teacher previews do not freeze content or increment counters.

Title/description/instructions also freeze so a revision remains a consistent definition. Availability, passcode, identity-field policy, total timer, shuffle, feedback, monitoring, and listing settings may change for future starts. Existing assessments capture their settings. A practice ticket captures settings when issued.

Duplicating creates an unlisted draft with a fresh share token, reset counters, no results, no passcode, and no opening/closing dates. Copy authored questions/settings and copy uploaded files to new private paths so each duplicated question/option owns its media reference independently.

## 4. Student flow, feedback, and scoring

The student reviews instructions and begins. The assessment start screen always asks for name and renders email/phone according to the teacher's settings. Before starting, disclose that the teacher receives the entered identity, IP address, browser information, answers, timing, and integrity events. Show one question at a time, with progress, applicable timers, accessible controls, and a clear submission action. There is no backward navigation or answer editing after submission. Permit an explicit skip/blank submission with confirmation; it earns zero.

Assessment drafts autosave with visible saving/saved/retry states. The current attempt item stores its ordered option codes, draft answer, save version, final answer, grade, and timing. Practice drafts stay in the current tab; label them “Saved in this tab,” never “Saved to server.” Both recover after a same-tab refresh. Closing/clearing tab storage loses practice progress and the assessment credential; cross-device recovery is deferred.

### Feedback policy

Two timing modes are supported from the MVP:

- `after_each`: lock the answer, show permitted feedback, then Continue. The question timer stops; the total timer continues while feedback is displayed.
- `at_end`: lock the answer and advance directly.

Both finish with a per-question review showing submitted answers and correct/partial/incorrect/unanswered status. Independently configure numeric score visibility and correct-answer visibility. Explanations may be shown only when correct-answer disclosure is on. Correctness is always shown at the configured feedback time even when numeric scores are hidden. No teacher-only result mode.

### Scoring

Use one PHP scoring engine for online assessment and practice:

- Single choice: full points for the correct choice; otherwise zero.
- Multi-select: `points × clamp(correctSelected / correctCount − incorrectSelected / incorrectCount, 0, 1)`.
- If no incorrect choices exist, the negative component is zero. Selecting all options can then earn full credit.
- Short text: trim Unicode whitespace, collapse repeated internal Unicode whitespace to one space, lowercase using Unicode-aware handling, and match exactly. Preserve punctuation and diacritics; do not apply accent folding or Unicode canonical normalization in v1.
- Round question awards half-up to two decimal places, sum those awards, and calculate percentage from the stored total. Use exact arithmetic, not binary floating-point scoring.

For two correct and two incorrect options, one correct selected earns 50%, both correct earn 100%, and all options earn 0%. Duplicate or foreign choice codes are invalid submissions rather than additional selections.

## 5. Assessment timing, reliability, and results

The effective active-question deadline is the earliest applicable per-question deadline, total-attempt deadline, and captured scheduled closing time. Display timers from server timestamps; changing the browser clock does not change eligibility.

- Expiration locks the last server-saved answer committed before the deadline, or an unanswered response if none exists.
- Late arrivals cannot replace an answer, irrespective of client timestamps.
- After a disconnected question expires, activate the next question only when requested. Its timer does not run while unseen. The overall and scheduled deadlines keep running.
- Total/scheduled expiration finalizes the attempt; unseen questions earn zero.
- Retried submissions cannot create another attempt, overwrite locked answers, or advance twice. Start keys, save versions, per-item submit keys/hashes, and attempt versions provide this protection without a general idempotency table.
- Manual closing, archiving, and soft deletion block new starts but let existing authorized sessions finish. Soft deletion is not a content purge.
- Changed schedule and other policies affect new starts only. Previously captured deadlines remain binding.

No artificial deadline is added to untimed assessments. They remain in progress until completion; expired framework sessions do not revoke a valid assessment bearer token.

The teacher sees assessment counts, status, entered name/email/phone, scores, percentage, elapsed time, question breakdowns, partial/correct/incorrect/unanswered counts, timing, and integrity incidents. The owner-only attempt detail also shows the captured IP address and browser user-agent. IP addresses may be shared, dynamic, proxied, or VPN-based, and user-agent strings can be spoofed; neither proves identity or cheating. Use finalized assessments for aggregate scores and show in-progress counts separately. Export finalized results as CSV with name/email/phone but without IP or user-agent. Time measurements are elapsed time, not proof of attention.

### Integrity monitoring

When enabled, disclose it before admission and record visibility changes, focus loss/return, supported fullscreen exits, and inactivity over 60 seconds. Fullscreen entry is optional and requires a user gesture. Deduplicate correlated events for summary purposes while retaining their event types.

Warn the student and show teachers a timeline. Never automatically fail, penalize, or submit based on incidents. Do not collect raw mouse coordinates, keystrokes, webcam/screen recordings, or device fingerprints. Events are incomplete evidence, not proof of cheating or its absence.

## 6. Anonymous practice and privacy

Practice submits answers over HTTPS for server grading without storing them in databases, server sessions, caches, or application logs. It creates no identity, IP, user-agent, or individual result record. Exclude request/response bodies from monitoring captures. The final review exists in the student's tab storage only.

Persist just an aggregate started-attempt counter and short-lived start deduplication records containing a quiz reference, random start-request key, and expiration. Count a successful start once; reload/resume/retry does not count again. A deliberate new start counts again. Do not call this a unique-person or completion count. Exclude previews.

Issue a 24-hour signed ticket; require a restart after expiration. Practice timers/progression are browser-managed and can be manipulated by a technically capable visitor. No authoritative score enters the teacher dashboard.

Immediate feedback returns permitted grading information. End feedback stores opaque encrypted review receipts in the browser until final review. This avoids normally revealing results early but is not exam security. Practice requires connectivity to fetch questions and grade; it is distinct from the later offline export.

The creator sees only total starts, with no participant list, individual results, completion rate, average scores, or per-session behavior. Existing tickets continue after manual closure until expiration.

## 7. Layout guidance from the prototype

Use `prototype-ui/index.html`, `app.js`, and `styles.css` as structural references, not production implementations or an exact visual mandate:

- Teacher shell: navigation/sidebar, header actions, overview cards, quiz list with filters.
- Builder: question rail, central question/choice editor, settings panel; Preview/Save/Publish actions.
- Assessment reports: summaries, attempt table, detail drawer linked to full response review.
- Student: focused start screen, question card, progress/timer, clear primary action, completion/review.
- Responsive layout: stacked builder panels and accessible mobile navigation, no essential data hidden without an alternative.

Add Profile navigation, assessment/practice badges, and listing controls. Practice management has a count panel instead of score reports. Public profiles use a simple biography header and quiz cards. Do not copy dummy analytics, placeholder notifications/AI/PDF buttons, client answer keys, hash-based navigation, or student “return to teacher” links.

## 8. Delivery milestones

1. Foundation: CI4/Shield, direct ownership, profiles, schema/migrations, authentication.
2. Authoring: lifecycle, builder, media, preview, sharing, public teacher pages.
3. Assessment: admission, order, autosave, locking, timers, scoring, both feedback modes, results/CSV.
4. Practice: anonymous tickets, server grading, receipts, start counter, privacy verification.
5. Launch: integrity monitoring, accessibility, race/load/security tests, backups, deployment verification.
6. Offline: standalone HTML and ZIP exports with local scoring/results only. No offline result import or synchronization.
7. Later: PDF, direct JSON/AI-assisted draft authoring, question banks, quotas and monetization.

Offline exports embed answer keys and use best-effort local timers. Warn that these are discoverable and not authoritative. Bundle supported local media; external video needs a placeholder/warning. Use shared language-neutral scoring fixtures for PHP/JavaScript parity. Add AI output only through validated drafts and teacher approval when that phase is implemented.

## 9. Acceptance criteria and defaults

Required verification before MVP launch:

- Direct-owner isolation for quizzes, nested content, media, and reports.
- Username validation/uniqueness, reserved routes, disabled profiles, listing eligibility.
- Clear saved-assessment versus anonymous-practice notices.
- No practice answer/result persistence or teacher access; deduplicated start counts.
- Practice ticket/receipt tampering, expiration, refresh, and complete final review.
- All scoring/rounding/text-normalization and feedback combinations, including JSON accepted answers and JSON multi-select answers.
- Static option codes remain opaque, unique within a question, and immutable after creation.
- Inline media replacement, duplication, signed delivery, and orphan cleanup preserve ownership and frozen content.
- Independent concurrent starts, stale drafts, retries, deadlines, reconnects, and cron reconciliation.
- Identity-field modes, trusted-proxy IP capture, bounded user-agent capture, and owner-only metadata access.
- Keyboard/mobile navigation, media delivery, upload/content validation, CSV safety, throttling.
- At least 50 simultaneous simulated students on staging; record latency, error rate, and infrastructure. No lost/duplicate answers or duplicate attempts for a retried start key.

Defaults: assessment mode; unlisted; profile disabled; English translation-ready UI; editable `Asia/Tashkent` timezone; required name, optional email, hidden phone; independent repeat starts allowed; timers, shuffle, and monitoring off; end feedback; scores shown; correct answers/explanations hidden.

Out of scope: organizations/workspaces/team roles, student accounts, cross-attempt identity grouping or per-person attempt limits, backward navigation, global discovery, manual grading, certificates, uploaded video, surveillance, billing implementation, and offline result imports.
