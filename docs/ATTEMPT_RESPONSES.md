# Assessment answer storage

The filename is retained for existing documentation links, but assessment answers are no longer stored in `attempts.responses`. The canonical model is one compact attempt summary plus one immutable `attempt_answers` row for every question presented to that attempt.

## Ownership of data

- `quiz_papers` stores immutable paper schema version 3: published quiz metadata, settings, questions, answer keys, explanations, and private media references. New papers omit the removed instructions property; older papers may retain it as ignored immutable data. A paper is shared by all starts that receive that published revision.
- `attempts` stores searchable run-level data: the paper, public identifier, hashed bearer credential, retry key, shuffle seed, disclosed identity, IP/browser details, lifecycle, authoritative deadline, integer score, and percentage.
- `attempt_answers` stores presented order and the student's confirmed answer. It has no foreign key to mutable live questions; `question_id` is interpreted against the attempt's bound paper.
- Practice stores no individual attempt or answer rows.

## `attempt_answers` state

Every assessment start inserts all question rows in the same transaction as the attempt:

| Column | Purpose |
| --- | --- |
| `attempt_id` | Owning assessment; cascades when the attempt is deleted. |
| `question_id` | Historical question identifier from the immutable paper. |
| `pos` | Exact presented question position after shuffling. |
| `presented_option_codes` | JSON array preserving the exact presented A/B/C option order; empty for short text. |
| `status` | `not_reached`, `answered`, or `skipped`. |
| `selected_option_codes` | Confirmed choice codes, or `NULL` for short text/skips/unreached rows. |
| `text_answer` | Confirmed short-text value, otherwise `NULL`. |
| `is_correct` | Server-calculated result; `NULL` until confirmation and `0`/`1` afterward. |
| `answered_at` | Server receipt time; `NULL` while not reached. |
| `client_answered_at` | Reported confirmation occurrence time; nullable for historical rows. Retried operations reuse it. |

The unique `(attempt_id, question_id)` and `(attempt_id, pos)` keys prevent duplicate question rows and ambiguous order. Database checks enforce the valid null/value combinations for each status. Application validation additionally rejects unknown questions, foreign or duplicate option codes, wrong answer shapes, and invalid text.

## Confirmation protocol

The browser keeps unconfirmed selections locally. Submit or Skip appends one durable local operation and calls:

```http
PUT /api/v1/player/assessments/{attemptPublicId}/answers/{questionId}
Authorization: Bearer {attemptCredential}
Content-Type: application/json

{
  "status": "answered",
  "answerCodes": ["5605a8cabf9b24d3"],
  "textAnswer": "",
  "clientAnsweredAt": "2026-10-01T12:01:00.000Z",
  "clientActivityAt": "2026-10-01T12:01:00.000Z"
}
```

The service locks the attempt, resolves expiry, validates against the bound paper, calculates correctness itself, and conditionally updates only a `not_reached` row. An identical retry is a no-op. A different retry for an already confirmed row returns `answer_locked`. Confirmations for different questions do not replace one another, so delayed or out-of-order delivery cannot erase newer answers.

The response is a small acknowledgement: `attemptId`, `questionId`, `answerStatus`, `receivedAt`, current status/deadline/reason, client activity, and late-sync flag. It contains neither the full paper nor other answers. Grading reads the bound paper directly, without shuffling or creating signed media URLs.

After all queued confirmations, Finish sends `finishReason`, `clientFinishedAt`, `clientActivityAt`, and `confirmedCount`. Normal completion requires every row; timeout or explicit Quit requires a confirmed prefix followed by unreached rows, and Quit may use an empty prefix. Missing confirmations return retryable `incomplete_sync`. The server regrades confirmed rows and returns the official result without a quiz payload. An acknowledged Quit is `status=completed`, `ended_reason=quit`; this means synchronization is final, not that every question was reached. Identical retries return the same result; conflicting final records are rejected. The browser removes only acknowledged operations and preserves newer local work.

## Deadlines and terminal state

`attempts.expires_at` is authoritative:

- Timed: `started_at + total timer`, capped by the paper's closing time.
- Untimed: accepted `client_activity_at + 8 hours`, capped by the paper's closing time.

Meaningful interactions, not receipt/retry time, extend inactivity. Untimed clients coalesce bearer-protected activity updates at most once per minute while changes exist. This internal deadline remains enforced but is not displayed as a student countdown, and routine activity waiting is excluded from pending-answer/Retry presentation. Server load, media, integrity, and passive visibility do not extend it. Timestamps are bounded by start and server time plus five seconds; continuous offline activity cannot be independently verified.

Every attempt endpoint checks expiry under the attempt lock. Expiry produces provisional `abandoned` results from received work. Delayed confirmations recorded before the browser deadline remain acceptable; `late_sync` flags reconciliation. Untimed activity may revive In progress. A validated final record becomes immutable `completed`, including timeout outcomes. `finished_at` is the effective ending time, while receipt timestamps remain separate. Invalid actions still commit legitimate lazy expiry; unrelated database failures roll back. Teacher report access also resolves overdue rows. No background worker is required.

## Historical review and scoring

Teacher and student review combine the immutable paper with `attempt_answers`. Presented positions and option-code order reproduce the exact historical question and A/B/C layout even if the live working copy is later edited or deleted. Per-quiz reporting defaults to all statuses and obtains response/correct counts with a grouped query limited to the displayed attempt IDs. Unfinished score fields remain null; running correctness is a projection only, and browser-local unsynchronized answers cannot appear until received.

Single choice and short text are full-or-zero. Multi-select uses exact-set, all-or-nothing comparison. Attempt `score` and `max_score` are integers; `percent` is an exact two-decimal summary. Client-reported correctness is never accepted.

## Deployment

Fresh databases import only [`schema.sql`](schema.sql). For an existing normalized-answer database, back up and stop student traffic. Apply [`offline-continuity-upgrade.sql`](offline-continuity-upgrade.sql) if it is not already installed, then apply the independent [`quit-attempt-upgrade.sql`](quit-attempt-upgrade.sql) and deploy matching code. The first adds offline-continuity fields; the second replaces only the ended-reason constraint. Both preserve records and are run manually in phpMyAdmin. Do not use the older destructive reset for this update. The application never runs schema changes automatically.

## Storage benchmark

The opt-in isolated-MySQL helper `tests/mysql/attempt-answers.php` measures table-plus-index allocation and single-row confirmation latency for 20-, 100-, and 500-question assessments. It validates the canonical schema first and refuses non-temporary MySQL data directories. Results depend on InnoDB page allocation, server version, row contents, buffer state, and sample size; row counts alone are not a storage estimate. Normalization intentionally trades more rows/index entries for small independent writes and simpler historical queries.

## Confirmation response measurements — 2026-10-01

Measured with PHP 8.4.16 and disposable MySQL **8.0.44** (the available local binary; the deployment target remains 8.4). `ConfirmationBenchmarkTest` uses 10 new answer confirmations for each no-media fixture. Byte counts include a JSON envelope and are uncompressed. The comparison reconstructs the previous full-state response shape using a full load; it is not an execution of the previous application release.

| Questions | Small acknowledgement bytes | Full-state bytes | Confirmation median ms | Additional full-load median ms |
| --- | ---: | ---: | ---: | ---: |
| 20 | 379 | 9,845 | 2.487 | 1.436 |
| 100 | 379 | 44,615 | 4.015 | 3.908 |
| 500 | 380 | 219,873 | 12.685 | 15.466 |

The acknowledgement eliminates repeated full-state downloads. Grading still decodes the bound paper, so server cost is not constant with quiz size. The separately measured full load includes authorization/expiry and projection; it is an indicative avoidable recovery-path cost, not a precise before/after end-to-end latency claim. Media-heavy papers may differ. Full start/resume/recovery responses remain intentionally complete.

Reproduce only against the guarded disposable server:

```sh
EDUTEST_CONFIRMATION_BENCHMARK=1 EDUTEST_TEST_MYSQL_SOCKET=/private/tmp/edutest-responses-mysql-XXXXXX/mysql.sock vendor/bin/phpunit --no-coverage tests/app/Services/ConfirmationBenchmarkTest.php
```

The normalized-row benchmark with 200 attempts per size measured 1,933,312 / 5,832,704 / 22,675,456 bytes of combined table/index allocation for 20 / 100 / 500 questions, respectively. Median single-row fixture confirmations were 1.209 / 1.243 / 1.450 ms. These synthetic storage fixtures are separate from the service benchmark above and are not production capacity estimates.
