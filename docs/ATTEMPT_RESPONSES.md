# Attempt answer storage

Assessment answers are stored in `attempts.answers`; there is no per-question answer table. The same attempt row also owns its immutable `snapshot`, answer version, timing, lifecycle, and result summary.

## Progress document

```json
[
  {
    "questionId": "12",
    "answerCode": "opaque-code",
    "answeredAt": "2026-10-25T12:00:00.000Z"
  },
  {
    "questionId": "13",
    "answerCode": null,
    "answeredAt": "2026-10-25T12:00:20.000Z"
  }
]
```

Progress is an ordered confirmed prefix of `snapshot.questions`. An option code is one of that question's presented stable codes. A null code with a timestamp means the student explicitly skipped it. Submitted/skipped entries are locked; no backtracking is supported.

The progress endpoint receives the complete prefix:

```json
{"answerVersion": 2, "answers": []}
```

A higher version replaces the stored prefix. An identical same-version retry is a no-op; a changed same-version body conflicts; a lower version is stale. The browser keeps the document in a localStorage outbox and retries silently.

## Final document

Finish sends every snapshot question in order:

```json
{
  "answerVersion": 4,
  "answers": [],
  "finishReason": "completed",
  "clientFinishedAt": "2026-10-25T12:03:00.000Z"
}
```

Reasons are `completed`, `exited`, or `expired`. For exit/expiry, unreached tail entries use `answerCode:null` and `answeredAt:null`. For normal completion, every entry has an answer timestamp (explicit skips still have a timestamp).

Finish is authoritative over the latest checkpoint. Under an attempt row lock the server validates shape, order, option membership, timestamps, version, and reason; calculates the result from `snapshot.questions[*].correctCode`; and atomically writes the complete answers, terminal status, reason, score, maximum, percentage, and finish time.

An identical Finish retry returns the stored official result. Changed terminal retries conflict. A delayed valid Finish may replace a provisional lazy `abandoned/expired` result and records `late_sync=1`.

## Expiry and reporting

An overdue in-progress attempt is lazily changed to `abandoned/expired` and graded from its latest checkpoint with the missing tail treated as skipped. This result is provisional.

Teacher reports decode `snapshot` and `answers` together. This reproduces exact historical wording and presentation even when the live quiz is edited, unpublished, or soft-deleted. CSV uses summary fields and protects formula-leading identity cells. Browser-local unsynchronized answers cannot appear in reports until received.
