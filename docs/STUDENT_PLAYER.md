# Student player

## Pages

- `/q/{shareToken}` is the introduction and admission page.
- `/q/{shareToken}/start` is the player.
- `/q/{shareToken}/results` reopens the result view.

There is no `/play` route. Direct access to `/start` without a matching browser-local attempt returns to the introduction. If the introduction finds an active local attempt for that share token, it redirects to `/start` automatically.

Name is required. Email and phone are optional and are rendered only when their captured quiz settings enable them.

## Start

The browser first requests a short-lived admission ticket. The ticket binds `quizId`, `quizVersion`, and a random `startKey`. Start is idempotent: a retry with the same successful ticket returns the attempt stored for `(quiz_id,start_key)` before checking whether the live quiz changed.

For a new start, the server locks the current published quiz, validates it, applies draw/question/option shuffling, inserts the attempt-owned snapshot, and returns its student projection with the attempt bearer credential and fixed `expiresAt`.

The projection includes answer keys by design so the loaded player can provide feedback and immediate offline results. It never exposes private media paths; those are replaced by short-lived signed URLs.

## Local state and no backtracking

Local state format 5 contains the attempt ID/credential, snapshot projection, fixed timestamps, answer prefix/version, current draft, terminal data, and progress/final outbox flags. It is scoped by share token in `localStorage`.

Only one tab may control a local run. A Web Lock is held per share token; another tab shows the blocked state. A missing Web Locks API is surfaced as a reduced-safety warning.

Selecting an option changes only the current draft. Submit or Skip appends one confirmed answer, increments `answerVersion`, locks that question, and queues a whole-prefix progress checkpoint. The player never returns to a confirmed question.

## Offline synchronization

After the quiz has loaded, all answering and local feedback can continue without a connection. Browser restarts recover the run from localStorage. Reopening the page itself still requires connectivity because the MVP has no service worker.

The synchronization worker serializes requests:

1. send the latest progress document when only progress is pending;
2. send Finish directly when final data is pending, because its complete document supersedes progress;
3. retain the same answer timestamps/version after network failure;
4. reconcile server state only when the attempt and versioned documents agree;
5. pause on version conflicts instead of discarding local work.

## Timer and finish

The timer is derived from the server's immutable `expiresAt`, never from a saved decrementing counter. It displays total minutes and seconds, including `120:00`. Closing, reopening, backgrounding, or changing the wall clock cannot legitimately reset the deadline.

At zero, the player freezes input, fills every unreached question with `{answerCode:null,answeredAt:null}`, renders the local result immediately, and queues Finish with reason `expired` and `clientFinishedAt = expiresAt`.

The Exit button performs the same tail fill with reason `exited` and the current client time. Normal completion requires every question to have a confirmed timestamp, including explicit skips.

The server is authoritative. It validates the final document against the stored snapshot and returns the official score/percentage and permitted feedback. A lazy `abandoned/expired` response is provisional; the player's valid queued Finish may still replace it and become a late official result.

## Result visibility

Visibility is captured in the attempt snapshot:

- `showScore` controls score/percentage.
- `showAnswers` controls selected and correct codes.
- `showExplain` controls explanations and is valid only with `showAnswers`.
- when all are false, the player shows only a neutral completion receipt.

`feedback = after_each` allows local per-question feedback from the loaded answer key. `at_end` defers feedback to the result screen. The server still calculates and stores the same official result in both cases.

## Privacy and media

The assessment stores disclosed identity, IP, browser agent, snapshot, checkpoints, result, and timing. The introduction explains this collection. Player endpoints are CSRF-exempt because they do not use teacher sessions; they require the player request boundary and bearer credential as applicable.

Media renewal is attempt-bound. The server resolves the requested key only inside that attempt's stored snapshot and issues a new signed URL. Editing or deleting the live quiz does not change the media references an existing attempt is authorized to request.
