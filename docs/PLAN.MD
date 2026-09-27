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

Teachers can create, edit, duplicate, publish, close, archive, and soft-delete quizzes. Each quiz has a private share link and may optionally appear on the teacher's public page.

Quiz settings include title, description, instructions, an optional cover image, mode, availability dates, an optional total timer entered in half-minute increments, shuffle options, feedback timing, and result visibility. Assessments may also require a passcode and collect email or phone.

Teacher assessment reporting is implemented as a read-only workspace. It includes historical active, archived, and soft-deleted assessment quizzes; basic workspace and per-quiz totals; filtered attempt lists; complete answer and integrity-event review; and filtered CSV exports. Practice quizzes remain absent from individual reports because practice stores only an aggregate start count.

## Questions and media

The MVP supports:

- Single-choice questions.
- Multi-select questions with proportional partial credit.
- Short-text questions with accepted answers.
- Optional explanations.
- JPEG, PNG, and WebP question or answer-choice images up to 5 MiB.
- MP3, M4A, OGG, and WAV question audio up to 20 MiB.
- Validated YouTube/Vimeo or direct HTTPS MP4 question video links.

Answer-choice media is image-only. Uploaded video, arbitrary embeds, question banks, fill-in-the-blank, essays, speaking responses, and manual grading are outside the MVP.

Each real student start binds the run to one immutable paper for the current quiz revision. Teachers may continue editing live content; existing attempts and results remain tied to the exact paper they received. Quiz mode alone locks after the first start.

## Student experience

Students see one question at a time with clear progress, optional total/closing timers, accessible controls, and a deliberate submit/skip action. Submitted answers cannot be edited and there is no backward navigation.

Assessment drafts synchronize in the background with visible saving, saved, pending, and retry states. A tab-local outbox permits continued answering during a disconnect. Practice progress is saved only in the current browser tab and is clearly labeled that way.

Feedback may appear after each answer or at the end. Correctness is shown at the configured time; score, correct answers, and explanations follow the teacher's visibility settings.

Both modes preload all questions and answer keys at start. Options carry opaque server-generated codes; matching those codes supports instant feedback but does not hide the answers from browser inspection. Visibility settings govern presentation, not secrecy. Assessment results are provisional until server confirmation; practice results are local only.

## Scoring

Every question has equal maximum credit of `1.00`; teachers do not configure question weights.

- Single choice earns `1.00` for the correct option or `0.00`.
- Multi-select uses proportional right-minus-wrong credit, clamped from `0.00` to `1.00`:

  ```text
  credit = max(0,
      correct selected / total correct
      − incorrect selected / total incorrect
  )
  ```

  When there are no incorrect options, the incorrect fraction is zero. A question must have at least one correct option. Duplicate or foreign option codes are invalid submissions.

  Example: with three correct and two incorrect options, two correct selections earn `0.67`, two correct plus one incorrect earn `0.17`, and selecting every option earns `0.00`.

- Short text earns `1.00` or `0.00`. Matching trims leading/trailing Unicode whitespace, collapses repeated internal Unicode whitespace to one space, and lowercases with Unicode-aware handling before exact comparison. Punctuation and diacritics are preserved; accent folding and Unicode canonical normalization are not applied in the MVP.
- Calculate with exact decimal arithmetic. Round each multi-select credit half-up to two decimal places, sum question credits, and calculate the percentage from the number of questions. Results use wording such as `7.50 / 10 questions`, never points.

One PHP scoring engine produces official assessment scores. Its JavaScript counterpart provides provisional assessment feedback and local practice scores; shared fixtures verify identical arithmetic and normalization.

## Assessment reliability and privacy

Assessment start and receipt timestamps are server-generated. Optional total and captured closing-time countdowns are enforced by the browser, including while disconnected. Late queued work is accepted and flagged, not claimed to have verified timing. Unfinished attempts are not automatically finalized while potentially unsynchronized work exists; abandoned attempts remain incomplete. Retried requests must not create duplicate attempts, overwrite locked answers, or advance twice.

Each successful student start captures or reuses an immutable paper for the current revision. Later authored changes apply to future starts; an existing authorized attempt can finish from its paper after the teacher edits, closes, archives, or soft-deletes the live quiz. Students can view their own finalized result with their tab-scoped bearer credential.

Before starting, students are told which identity, network, browser, answer, timing, and integrity information the teacher receives. Integrity events are warnings and timeline signals only; they never automatically fail or penalize a student.

Teacher exports include finalized assessment identity/result fields but exclude IP addresses and browser agents. Those sensitive fields are restricted to owner-authorized attempt detail.

## Practice privacy

Practice creates no individual database result, identity, IP address, browser-agent record, server-side answer history, or teacher-visible session. All grading and review are local; answers are never submitted. Signed tickets authorize starts/media without a PHP session. Only expiring deduplication keys and the aggregate count are persisted. Application debug/caching facilities must not retain student request/response bodies; deployment access logs require separate redaction.

The aggregate counter represents deduplicated starts, not unique people or completions.

## Delivery milestones

1. Foundation — CI4, project-owned session authentication/account identity, and the canonical SQL schema.
2. Authoring — teacher dashboard/library, quiz lifecycle, builder, private media, preview, and the stable public quiz-information page. Public teacher profiles remain deferred.
3. Student player — assessment admission, background synchronization, offline-friendly timers, official scoring, feedback, and student results; anonymous practice with local grading and start counting.
4. Teacher reporting — assessment result pages, basic totals, attempt review, and CSV exports (implemented).
5. Launch — integrity events, accessibility, race/load/security tests, backups, and cPanel deployment.
6. Later — public teacher profiles, standalone offline quiz export, PDF, validated AI-assisted drafts, question banks, quotas, and monetization.

## Defaults and exclusions

Defaults: assessment mode, unlisted quiz, disabled public profile, English translation-ready UI, `Asia/Tashkent` timezone, required name, optional email, hidden phone, no total timer/shuffle/integrity monitoring, feedback at end, score visible, and correct answers/explanations hidden.

The MVP excludes organizations/workspaces, team roles, student accounts, per-person attempt limits, global discovery, leaderboards, certificates, surveillance, billing, uploaded video, and offline result imports.
