# EduTest product plan

Status: product vision and delivery plan. It describes intended behavior, not proof that a feature is implemented.

## Vision

EduTest helps independent teachers, education centers acting through a teacher account, and recruiters create and share focused quizzes. The MVP supports two modes:

| | Assessment | Practice |
| --- | --- | --- |
| Purpose | Teacher evaluates students | Visitor checks knowledge |
| Identity | Name required; email/phone configurable | Anonymous |
| Saved on server | Attempts, answers, and results | No individual answers or results |
| Teacher sees | Results and analytics | Aggregate start count only |
| Authority | Server confirms scoring; offline timing is client-enforced | Browser progress and scoring are non-authoritative |

## Teacher experience

Teachers register with email/password, may provide a phone number, manage a public profile, and own quizzes directly. Registration signs the teacher in immediately; email verification and password recovery are deferred beyond the initial authentication foundation. Public profiles are disabled by default and show only the display name, biography, and explicitly listed available quizzes.

Teachers can create, edit, duplicate, publish, close, archive, and soft-delete quizzes. Each quiz has a private share link and may optionally appear on the teacher's public page.

Quiz settings include title, description, instructions, an optional cover image, mode, availability dates, optional timers, shuffle options, feedback timing, and result visibility. Assessments may also require a passcode and collect email or phone. Teacher result reports and exports are deferred beyond the student-player milestone.

## Questions and media

The MVP supports:

- Single-choice questions.
- Multi-select questions with partial scoring.
- Short-text questions with accepted answers.
- Optional explanations and per-question timers.
- JPEG, PNG, and WebP images up to 5 MiB.
- MP3, M4A, OGG, and WAV audio up to 20 MiB.
- Validated YouTube/Vimeo links or direct HTTPS MP4 links.

True/false is a single-choice preset. Uploaded video, arbitrary embeds, question banks, ordering, fill-in-the-blank, essays, speaking responses, and manual grading are outside the MVP.

The first real student start freezes authored quiz content. A teacher duplicates a frozen quiz to make content changes without altering existing sessions or results.

## Student experience

Students see one question at a time with clear progress, applicable timers, accessible controls, and a deliberate submit/skip action. Submitted answers cannot be edited and there is no backward navigation.

Assessment drafts synchronize in the background with visible saving, saved, pending, and retry states. A tab-local outbox permits continued answering during a disconnect. Practice progress is saved only in the current browser tab and is clearly labeled that way.

Feedback may appear after each answer or at the end. Correctness is shown at the configured time; score, correct answers, and explanations follow the teacher's visibility settings.

Both modes preload all questions and answer keys at start. Options carry opaque server-generated codes; matching those codes supports instant feedback but does not hide the answers from browser inspection. Visibility settings govern presentation, not secrecy. Assessment results are provisional until server confirmation; practice results are local only.

## Scoring

- Single choice earns full points only for the correct option.
- Multi-select uses proportional right-minus-wrong scoring:

  ```text
  award = max(0, question points × (
      correct selected / total correct
      − incorrect selected / total incorrect
  ))
  ```

  Each correct option therefore contributes `question points / total correct`; each incorrect option subtracts `question points / total incorrect`. When `total incorrect` is zero, treat the incorrect fraction and penalty as zero rather than dividing by zero. A question must have at least one correct option; reject invalid authoring data on the server and mirror that validation in the UI. Duplicate or foreign option codes are invalid submissions.

  Example: a 10-point question with three correct and two incorrect options awards `6.67` for two correct selections, `1.67` for two correct plus one incorrect, and `0.00` when every option is selected.

- Short text trims leading/trailing Unicode whitespace, collapses repeated internal Unicode whitespace to one space, and lowercases with Unicode-aware handling before exact comparison. Preserve punctuation and diacritics; do not apply accent folding or Unicode canonical normalization in the MVP.
- Calculate with exact decimal arithmetic. Do not round per-option values; round each final question award half-up to two decimal places, then sum the rounded awards and calculate the percentage.

One PHP scoring engine produces official assessment scores. Its JavaScript counterpart provides provisional assessment feedback and local practice scores; shared fixtures verify identical arithmetic and normalization.

## Assessment reliability and privacy

Assessment start and receipt timestamps are server-generated. Question, total, and captured closing-time countdowns are enforced by the browser, including while disconnected. Late queued work is accepted and flagged, not claimed to have verified timing. Unfinished attempts are not automatically finalized while potentially unsynchronized work exists; abandoned attempts remain incomplete. Retried requests must not create duplicate attempts, overwrite locked answers, or advance twice.

The first successful student start freezes content and media. Availability and policy changes apply to future starts; an existing authorized attempt can finish after a teacher closes, archives, or soft-deletes the quiz. Students can view their own finalized result with their tab-scoped bearer credential.

Before starting, students are told which identity, network, browser, answer, timing, and integrity information the teacher receives. Integrity events are warnings and timeline signals only; they never automatically fail or penalize a student.

Teacher exports include finalized assessment identity/result fields but exclude IP addresses and browser agents. Those sensitive fields are restricted to owner-authorized attempt detail.

## Practice privacy

Practice creates no individual database result, identity, IP address, browser-agent record, server-side answer history, or teacher-visible session. All grading and review are local; answers are never submitted. Signed tickets authorize starts/media without a PHP session. Only expiring deduplication keys and the aggregate count are persisted. Application debug/caching facilities must not retain student request/response bodies; deployment access logs require separate redaction.

The aggregate counter represents deduplicated starts, not unique people or completions.

## Delivery milestones

1. Foundation — CI4, project-owned session authentication/account identity, and the canonical SQL schema.
2. Authoring — teacher dashboard/library, quiz lifecycle, builder, private media, preview, and the stable public quiz-information page. Public teacher profiles remain deferred.
3. Student player — assessment admission, background synchronization, offline-friendly timers, official scoring, feedback, and student results; anonymous practice with local grading and start counting.
4. Teacher reporting — assessment result pages, analytics, and CSV exports (deferred).
5. Launch — integrity events, accessibility, race/load/security tests, backups, and cPanel deployment.
6. Later — public teacher profiles, standalone offline quiz export, PDF, validated AI-assisted drafts, question banks, quotas, and monetization.

## Defaults and exclusions

Defaults: assessment mode, unlisted quiz, disabled public profile, English translation-ready UI, `Asia/Tashkent` timezone, required name, optional email, hidden phone, no timers/shuffle/integrity monitoring, feedback at end, score visible, and correct answers/explanations hidden.

The MVP excludes organizations/workspaces, team roles, student accounts, per-person attempt limits, global discovery, leaderboards, certificates, surveillance, billing, uploaded video, and offline result imports.
