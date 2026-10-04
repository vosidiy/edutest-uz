# EduTest product plan

Status: product vision and delivery plan. It describes intended behavior, not proof that a feature is implemented.

## Vision

EduTest helps independent teachers, education centers acting through a teacher account, and recruiters create and share focused quizzes. The MVP supports two modes:

| | Assessment | Practice |
| --- | --- | --- |
| Purpose | Teacher evaluates students | Visitor checks knowledge |
| Identity | Name required; email/phone configurable | Anonymous |
| Saved on server | Attempts, answers, and results | No individual answers or results |
| Teacher sees | Results and basic totals | Aggregate start count only |
| Authority | Server confirms scoring; offline timing is client-enforced | Browser progress and scoring are non-authoritative |

## Teacher experience

Teachers register with email/password, may provide a phone number, manage a public profile, and own quizzes directly. Registration signs the teacher in immediately; email verification and password recovery are deferred beyond the initial authentication foundation. Public profiles are disabled by default and show only the display name, biography, and explicitly listed available quizzes.

Teachers can create, edit, duplicate, publish, close, archive, and soft-delete quizzes. New quizzes receive a nine-digit share code and may optionally appear on the teacher's public page. A share code is a convenient locator rather than an access-control secret; teachers should configure a passcode when assessment access must be restricted.

Quiz settings include title, description, instructions, an optional cover image, mode, availability dates, an optional total timer entered in half-minute increments, shuffle options, feedback timing, and result visibility. Assessments may also require a passcode and collect email or phone.

The teacher home is one centered dashboard with a logo/account header and a paginated product-card quiz library. The MVP filters by status and mode, sorts by updated date, creation date, title, or finalized attempts, and keeps lifecycle actions on each card. All non-deleted quizzes, including archived quizzes, appear by default; Trash is an explicit status filter. Cards show finalized assessment attempts and aggregate Practice starts without workspace-wide summary metrics, title search, or report-eligibility filtering. Login and registration open this dashboard; old library and Results-overview links redirect there.

Quiz editing and response review use a dedicated workspace without the dashboard header. A shared sticky header links Builder and Responses for Assessment quizzes or quizzes with historical assessment attempts; Practice-only quizzes omit the tabs. Historical archived/deleted reports remain accessible, with restoration guidance instead of an unusable Builder link.

Teacher assessment reporting is implemented as a read-only workspace. Dashboard cards provide eligible quiz links, while quiz-scoped reporting includes per-quiz totals, filtered attempt lists, complete answer and integrity-event review, and filtered CSV exports for current or historical assessment activity. A quiz report defaults to all statuses, newest first, and shows received/confirmed progress plus running correctness for unfinished attempts. In-progress is saved state, not proof that the student is online; offline work remains invisible until uploaded. Practice runs remain absent from individual reports because practice stores only an aggregate start count.

## Questions and media

The MVP supports:

- Single-choice questions.
- Multi-select questions with exact-set, all-or-nothing scoring.
- Short-text questions with accepted answers.
- Optional explanations.
- JPEG, PNG, and WebP question or answer-choice images up to 5 MiB.
- MP3, M4A, OGG, and WAV question audio up to 20 MiB.
- Validated YouTube/Vimeo or direct HTTPS MP4 question video links.

Answer-choice media is image-only. Uploaded video, arbitrary embeds, question banks, fill-in-the-blank, essays, speaking responses, and manual grading are outside the MVP.

Publishing creates an immutable paper and makes it the quiz's active student version. Teachers may continue editing the working copy—including switching between Assessment and Practice—without changing what new students receive. **Publish changes** validates the complete working copy and atomically activates its next paper. Existing attempts and Practice runs remain tied to the exact paper they received.

## Student experience

Students see one question at a time with clear progress, optional total/closing timers, accessible controls, and a deliberate submit/skip action. Submitted answers cannot be edited and there is no backward navigation.

Each assessment creates one `attempt_answers` row per paper question at start. Rows preserve presented order and move once from `not_reached` to `answered` or `skipped`; confirmed rows never change. Quiz content and answer keys remain in the bound immutable paper. Detailed historical review remains available without storing question text repeatedly.

Only confirmed answers and explicit skips synchronize in the background. Routine coalesced activity traffic is silent; pending/upload/retry states refer to answer or final-record delivery, and unconfirmed selections stay local. A serialized browser outbox retries each question independently, then queues Finish after every confirmation. Assessment credentials, saved identity projection, paper data, progress, and pending confirmations use browser `localStorage` so a browser restart can resume; Practice remains local and anonymous.

Admission retries keep the first successfully saved identity and attempt; changed retry fields do not replace it. Supported browsers prevent two open tabs on the same site from actively running the same quiz, including while assessment results are waiting to sync. Closing a tab releases that coordination; different browsers/profiles/devices remain independent. This is not student identification or a per-person attempt limit.

Feedback may appear after each answer or at the end. Correctness is shown at the configured time; score, correct answers, and explanations follow the teacher's visibility settings. When all three visibility settings are disabled, students see only completion and synchronization status, without correctness labels, answer review, or scoring announcements.

Both modes preload all questions and answer keys at start. Options carry opaque server-generated codes; matching those codes supports instant feedback but does not hide the answers from browser inspection. Visibility settings govern presentation, not secrecy. Assessment results are provisional until server confirmation; practice results are local only.

## Scoring

Every question has equal weight; teachers do not configure question weights.

- Single choice is correct only when the selected option is the correct option.
- Multi-select is correct only when the selected codes exactly equal the complete correct-code set. Missing a correct option or choosing any incorrect option makes the whole question wrong. Duplicate or foreign codes are rejected.
- Short text is full-or-zero. Matching trims leading/trailing Unicode whitespace, collapses repeated internal Unicode whitespace to one space, and lowercases with Unicode-aware handling before exact comparison. Punctuation and diacritics are preserved; accent folding and Unicode canonical normalization are not applied in the MVP.
- The score is the integer number of correct questions; the maximum is the question count. Percentage remains exact to two decimal places. Results use wording such as `7 / 10 questions`, never points or partial credit.

One PHP scoring engine produces official assessment scores. Its JavaScript counterpart provides provisional assessment feedback and local practice scores; shared fixtures verify identical arithmetic and normalization.

## Assessment reliability and privacy

Timed runs use the earlier of total timer and published closing time. The player displays only a published total-timer or scheduled-closing countdown; the internal inactivity deadline is never shown or announced. Untimed runs still expire after eight hours without meaningful quiz interaction, capped by closing time. Selection, typing, Submit, Skip, Next, and explicit Resume before expiry count as activity; background requests do not. The browser checks expiry before interactions and restores ended work without granting fresh time. Unsubmitted drafts never contribute to timeout scores.

The server lazily marks overdue runs Abandoned using received answers. This is provisional: delayed confirmations recorded before the browser deadline may reconcile the run, including after abandonment. A final browser record becomes Completed only when all declared confirmations are received and officially graded; timeout is distinguished by its finish reason. Offline timing and continuous activity rely on client-reported timestamps and cannot be independently verified. Recovered late work is flagged in teacher reports; without reconnection, the teacher sees only received work.

Local progress and the outbox survive normal browser restarts. Reopening requires connectivity; no service worker or guaranteed offline page reload is provided. Local work is restored before server reconciliation and is never replaced by a less complete abandoned result. Persistent red connection warnings explain pending uploads; retry is automatic with bounded backoff and a manual option after a failed communication/upload. Starting another assessment requires acknowledgement of the current final record. Students may explicitly Quit after confirmation: confirmed answers are finalized, the current draft remains unanswered, and a fresh admission is offered only after required uploads and Finish are acknowledged. Storage failures allow in-memory play but warn that restart recovery is unavailable.

Each successful student start binds to the quiz's current published paper. Saved-but-unpublished authoring changes remain invisible. An existing authorized attempt can finish from its paper after the teacher edits, republishes, closes, archives, or soft-deletes the live quiz. Students can view terminal results with the attempt bearer credential stored in that browser.

Before starting, students are told which identity, network, browser, answer, timing, and integrity information the teacher receives. Optional integrity monitoring records only when the quiz tab is hidden and when an active fullscreen session is exited. Fullscreen is recommended but does not block access, and integrity events never automatically fail or penalize a student.

Teacher exports include finalized assessment identity/result fields but exclude IP addresses and browser agents. Those sensitive fields are restricted to owner-authorized attempt detail.

## Practice privacy

Practice creates no individual database result, identity, IP address, browser-agent record, server-side answer history, or teacher-visible session. All grading and review are local; answers are never submitted. Signed tickets authorize starts/media without a PHP session. Only expiring deduplication keys and the aggregate count are persisted. Application debug/caching facilities must not retain student request/response bodies; deployment access logs require separate redaction.

The aggregate counter represents deduplicated starts, not unique people or completions.

## Delivery milestones

1. Foundation — CI4, project-owned session authentication/account identity, and the canonical SQL schema.
2. Authoring — unified teacher dashboard/library and shared quiz workspace, quiz lifecycle, builder, private media, preview, and the stable public quiz-information page. Public teacher profiles remain deferred.
3. Student player — assessment admission, background synchronization, offline-friendly timers, official scoring, feedback, and student results; anonymous practice with local grading and start counting.
4. Teacher reporting — assessment result pages, basic totals, attempt review, and CSV exports (implemented).
5. Launch — integrity events, accessibility, race/load/security tests, backups, and cPanel deployment.
6. Later — public teacher profiles, standalone offline quiz export, PDF, validated AI-assisted drafts, question banks, quotas, and monetization.

## Defaults and exclusions

Defaults: assessment mode, unlisted quiz, disabled public profile, an Uzbek marketing landing with an English translation-ready application UI, `Asia/Tashkent` timezone, required name, optional email, hidden phone, no total timer/shuffle/integrity monitoring, feedback at end, score visible, and correct answers/explanations hidden.

The MVP excludes organizations/workspaces, team roles, student accounts, per-person attempt limits, global discovery, leaderboards, certificates, surveillance, billing, uploaded video, and offline result imports.
