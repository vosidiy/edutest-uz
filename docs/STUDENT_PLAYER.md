# Student player

The public introduction is `/q/{shareToken}`; `/play` and `/results` below that link render the player and result. New quizzes use nine-digit share codes and legacy 64-character hexadecimal links remain valid. A share code is a locator, not a secret; use a passcode for restricted assessment admission.

## Published paper boundary

Students never start from mutable builder tables. Initial **Publish** and later **Publish changes** create immutable paper schema version 3 and set `quizzes.current_paper_id`. Public introductions, admission tickets, Assessment starts, and Practice starts read that paper only. Saving the working copy does not change the student version.

The paper JSON includes published metadata, settings, schedule, total timer, shuffle/feedback/visibility/integrity policy, questions, answers, explanations, and private media references. New papers omit the removed instructions property; older version-3 papers may retain it as ignored immutable data. The published passcode hash is stored separately in `quiz_papers.passcode_hash` and is never returned. Existing attempts and Practice credentials remain bound to their paper after live edits or lifecycle changes.

## Student API

All routes are under `/api/v1/player`, require `X-EduTest-Player: 1`, use same-origin JSON requests with cookies omitted, and return `{data, meta}` or `{error, meta}` envelopes.

| Method and endpoint | Credential and behavior |
| --- | --- |
| `GET tickets/{shareToken}` | Checks the current published paper and issues a signed 15-minute admission ticket. |
| `POST starts` | Starts/retries Assessment or Practice. New Assessments validate identity and paper passcode. |
| `GET assessments/{attemptId}` | Bearer-authenticated resume, authoritative deadline, saved confirmations, paper, and terminal result. |
| `PUT assessments/{attemptId}/answers/{questionId}` | Confirms one answer or explicit skip. Identical retry is a no-op; a changed confirmed answer is rejected. |
| `POST assessments/{attemptId}/finish` | Validates the final confirmed count/prefix and returns the official result without resending the paper. Normal completion requires all rows; timeout or explicit Quit allows an unanswered suffix. |
| `GET assessments/{attemptId}/results` | Returns a terminal result only. |
| `POST assessments/{attemptId}/activity` | Accepts coalesced meaningful untimed activity without treating receipt as activity. |
| `POST assessments/{attemptId}/events` | Accepts idempotent `tab_hidden` and `fullscreen_exit` events when enabled. |
| `POST assessments/{attemptId}/media` | Renews media URLs without extending inactivity. |
| `POST practice/media` | Renews a paper-bound Practice credential/media without storing progress. |

Signed admission tickets carry paper revision, shuffle seed, and a random start key. The unique `(quiz_id, start_key)` constraint makes a successful start retry-safe. Changed or omitted retry identity/passcode fields do not replace the original saved identity. A ticket for a no-longer-current paper returns `quiz_changed` before creating a new run.

## Browser persistence and synchronization

The player stores its attempt ID, bearer credential, saved name/email projection, paper projection, progress, and retry queue in `localStorage` under the quiz share code. This enables recovery after a browser/tab restart. The identity projection is returned only by bearer-authenticated Assessment start/load responses and is rendered as text in the player/results header; Practice remains anonymous. Older format-4 states without it receive it on recovery. A different browser/profile has different storage and can create a separate attempt. Clearing storage can also create another attempt; this is not student identification or an attempt limit.

Unconfirmed selections never reach the server. Submit and Skip atomically persist the local question and its queued confirmation before advancing. Each operation retains its original client occurrence/activity timestamps across retries. Confirmations are sent serially and retried until acknowledged; Finish is queued after every answer operation. A lost acknowledgement safely repeats the same row update. Confirmations for different questions are independent, so out-of-order network delivery cannot overwrite another answer.

Where available, an exclusive Web Lock prevents two same-origin tabs from actively running the same share code. A blocked tab cannot start, answer, synchronize, submit integrity events, or renew media. The lock survives backgrounding/offline work and is released after confirmed Assessment completion, local Practice completion, or page exit. Unsupported browsers fail open with a notice.

While blocked, the logo header remains and the main content shows only the plain-text quiz title, another-tab message, and Retry button. The introduction/form, player content, and other notices are hidden. Students switch tabs manually. Retry attempts are serialized; after ownership becomes available, the tab reloads the latest local run and resumes valid active work or restores the introduction for ended work. Retry never replays an earlier Start click or discards the other tab's progress.

## Deadlines and resuming

Opening a shared introduction silently reconciles and attempts to upload saved Assessment answers and Finish, including offline/provisionally abandoned work. Only valid unfinished, unexpired In progress runs auto-resume; Practice uses its local deadline. Completed/expired work stays on the introduction with no old-run technical messages. Opening never extends inactivity. Upload retries do not block Start new quiz: it immediately discards the old run and invalidates stale responses. Pending work otherwise remains until acknowledged; there is no separate local attempt history.

- Timed: the earlier of total timer expiry and the paper's closing time; closing the tab never pauses it. The applicable authored timer/closing deadline is shown.
- Untimed: eight hours after meaningful client activity, capped by closing. This inactivity deadline is enforced but never displayed as a countdown or announced as an advance warning. Selection/typing, Submit, Skip, Next, and Quit before expiry count. Automatic resume, background requests, passive visibility, media, and integrity traffic do not.

The browser checks expiry before interactions and immediately on restore. Expiry persists a final timeout record: only previously confirmed answers count, never the current unsubmitted draft. Returning after expiry restores ended work rather than granting extra time.

Server expiry lazily produces a provisional `abandoned` record scored from received rows. It does not discard delayed work. Confirmations carry `clientAnsweredAt` and `clientActivityAt` and may arrive after expiry if recorded before the browser deadline. Untimed activity can reconcile a provisionally abandoned run to In progress; only reported occurrence time extends inactivity. Timing must be no earlier than start and no more than five seconds ahead of the server. Offline timing and activity history remain client-enforced and cannot be independently verified.

Finish carries `finishReason`, `clientFinishedAt`, `clientActivityAt`, and `confirmedCount`. The declared count/presented prefix must match received rows; missing work returns retryable `incomplete_sync`. Normal completion requires every row. Quit accepts a prefix including zero, preserves confirmed answers/skips, leaves the current draft unreached, and stores `status=completed` plus `ended_reason=quit`. The player asks for confirmation, may queue Quit offline, and offers Start again only after pending answers and Finish are acknowledged; Practice Quit is entirely local. An already expired browser records the applicable timeout instead. Accepted final records cannot reopen. Abandoned reports may change after reconciliation and recovered work is marked late. No reconnection means the teacher retains only received answers. No background worker is required.

Local recovery preserves confirmations, drafts, position, and provisional results during reconciliation. On the introduction, valid Start new quiz submission deliberately discards previous local work without confirmation or waiting for synchronization, even after rejected access. Invalid forms and denied tab ownership preserve old work. Fresh identity/passcode admission remains required. No server completion is fabricated; discarded unsent work cannot reach the teacher. Direct player views preserve unsupported queues with a recovery notice, but an explicit new start may discard them.

A fresh start clears only this share code's previous state, admission/retry records, runtime media object URLs, timers, and worker/error state before requesting online admission. Other quizzes, login sessions, and browser-wide caches remain untouched. Failed admission does not restore discarded work. A pending ticket for the new admission (with no previous run) survives ambiguous-response retries for deduplication; a previous Practice result's retry ticket does not. Stale responses cannot modify replacement state. Practice never uploads answers/results. Official-result acknowledgements still refresh displayed scores and confirmation status.

One coordinator handles recovery and outgoing requests both in the player and silently on the introduction. Confirmations retain their original timestamps and are sent before Finish. Answering shows no synchronization status, retry controls, access-rejected alerts, or automatic sync-conflict dialogs. Assessment results have a single concise status: Result saved, Waiting to save, or Could not save result; failures offer manual Retry, and conflicting saved progress can be reviewed explicitly there. Transient failures retry with bounded backoff; permanent authorization/validation failures pause until explicit retry or a later reload. Practice remains local. Admission validation, media interactions, and tab ownership retain their essential controls.

Browser storage survives ordinary restarts. Reopening the page requires connectivity: no service worker or guaranteed offline reload is added. Storage failure retains in-memory play but warns that restart recovery is unavailable. Uncached media and external video may require a connection.

Practice stores no identity, answer, result, IP, browser, or individual attempt record. Progress, feedback, scoring, and stale handling remain local. `practice_keys` only deduplicate starts and bind renewable media to a paper; `practice_starts` is an aggregate count.

## Editing player markup

The introduction is authored in `app/Views/public/quiz.php`: a cover-left/details-right card, stacked at the existing small-screen breakpoint, using `public/images/placeholder.jpg` when no cover exists. It shows teacher attribution, title/description, question count, time limit, mode, schedule/availability, and admission fields. Assessment collection and enabled monitoring disclosures/fullscreen controls live in collapsed native Privacy details. Practice and timer helper paragraphs are omitted. Start new quiz retains its decorative arrow when its translated label changes.

Edit `app/Views/student/partials/player_templates.php` to change the dynamic player's HTML structure or CSS classes. The shared student layout includes these inert templates on introduction, play, and results pages. Keep the named `data-slot` and `data-action` hooks when changing markup: `public/js/player-view.js` uses them to populate text and connect controls. Nested template placeholders are replaced with cloned content without adding wrappers. Rendering functions receive display data and callbacks; `player.js` retains the quiz lifecycle, persistence, scoring decisions, and synchronization. Dynamic authored text uses `textContent`, and input/media values use DOM properties. Review content and its media are still populated only on first expansion.

## Scoring and feedback

Every question has equal weight. Single choice and normalized short text are full-or-zero. Multi-select is correct only when the selected set exactly equals the correct set. Assessment finish regrades from the immutable paper and stores integer `score`/`max_score` plus a two-decimal percentage; client correctness is never trusted.

Both feedback modes intentionally deliver answer keys to the browser. `showScore`, `showAnswers`, and `showExplain` control presentation, not secrecy. Practice results are local; Assessment results remain provisional until the server acknowledges all confirmations and Finish. When score, answers, and explanations are all hidden, the player shows only completion/synchronization status, without correctness labels, review, or scoring announcements. Screenshots of permitted local results are informal evidence, not automatic official grade changes.

The contextual student header shows mode, question position, and the single Quit action only while answering. Visible-score completion cards use 🏆 at 90–100%, 🤩 at 80–89.99%, 👍 at 70–79.99%, 😊 at 60–69.99%, 🥺 at 50–59.99%, and 😢 below 50%, alongside separate Correct, Wrong, and Unanswered totals from the displayed result items. Provisional totals are replaced when an official Assessment result arrives. When numeric scores are hidden, the card uses a neutral 🎉 and omits percentage-derived presentation.

## Privacy, media, and configuration

An untracked `.env` `encryption.key` is required for admission, bearer derivation, and signed media. Private paths never enter API data. Paper media stays retained while the current quiz, an Assessment attempt, or an unexpired Practice key references its paper.

Assessment identity, answers, result, timing, IP address, browser agent, and enabled integrity observations are disclosed before start. IP and browser agent appear only on owner-authorized attempt detail and are excluded from CSV. Fullscreen is advisory and integrity events never apply an automatic penalty.

## Deployment and verification

Apache/FastCGI may provide Authorization only as `REDIRECT_HTTP_AUTHORIZATION` (or a repeated internal-redirect prefix). The public rewrite rules preserve the header before routing; the player reads these exact server variables only when the normal Authorization header is absent. A present malformed header cannot fall back to a different credential. Client headers named Redirect-Http-Authorization, query parameters, and teacher sessions cannot authorize an attempt. A loopback MAMP CGI/FastCGI probe reproduced the missing normal header and verified the fallback with synthetic credentials, then was removed; it did not access the database or expose credentials. Teacher Responses requires manual page refresh after a successful Finish; received answers alone do not mean Completed.

Fresh databases import only [`schema.sql`](schema.sql). Existing normalized-answer databases first apply [`offline-continuity-upgrade.sql`](offline-continuity-upgrade.sql) when needed, then apply the independent [`quit-attempt-upgrade.sql`](quit-attempt-upgrade.sql) to allow explicit Quit. Both are manual phpMyAdmin operations performed after backup, with student traffic paused and matching code deployed; both preserve existing records. The older destructive published-paper reset is not required for this deployment. Never run tests against the imported MAMP development database.

```sh
vendor/bin/phpunit --do-not-fail-on-warning
node --test tests/js/*.test.mjs
node tests/routes-check.mjs
node tests/browser/player-offline.mjs
```

The PHP tests adapt the canonical schema into isolated SQLite. Optional MySQL checks require a separately initialized temporary server under `/private/tmp/edutest-responses-mysql-*`; the guard rejects other data directories. Functional browser verification should cover restart recovery, delayed confirmations, terminal-attempt restart, Web Locks, late recovery, expiry without draft credit, offline queues, feedback/visibility combinations, and media renewal.

The offline browser harness uses real PHP views/player modules with a loopback fixture API and an isolated Chrome profile, not the development database. It checks behavior only, without screenshots or responsiveness/visual assertions. The SQL upgrade and concurrent confirmation/expiry/Finish tests require the guarded temporary MySQL server. Verification on 2026-10-02 ran 95 PHP tests (674 assertions; four opt-in MySQL tests skipped in the default run), 46 JavaScript tests, 46 route/filter checks, all PHP/changed-JavaScript syntax checks, and functional Chrome recovery/identity/timer/Quit checks. Three focused opt-in MySQL tests (48 assertions) separately passed upgrade preservation and concurrency. Available isolated MySQL was 8.0.44; MySQL 8.4-specific validation remains a deployment check.
