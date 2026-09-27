# Assessment response storage

One `attempts.responses` JSON document stores an assessment's ordered progress and answers. `quiz_papers.definition` remains immutable and shared. Identity, status, score, percentage, and dates remain ordinary attempt columns for reporting and CSV. Practice stores no individual responses.

## Internal format

The root is `{"schemaVersion":1,"items":[...]}`. Array order is the presented question order. Every item includes `q` (historical string question ID) and `o` (ordered option codes, empty for short text). Pending/default values are omitted and expanded by `AttemptResponses`; no consumer should parse storage independently.

| Key | Meaning | Omitted default |
| --- | --- | --- |
| `a` | Selected option codes | Empty array |
| `s` | Status | `pending` |
| `b` | Response start time | null |
| `l` | Lock/receipt time | null |
| `r` | Lock reason | null |
| `t` | Short-text answer | null |
| `v` | Per-item save version | 0 |
| `u` | Last saved time | null |
| `k` | Submission key | null |
| `h` | SHA-256 submission hash, hexadecimal | null |
| `g` | Grading outcome | null |
| `c` | Exact decimal credit, `0.00`–`1.00` | null |

Stored timestamps are UTC database-format strings, including microseconds when available. The existing API serializer converts public dates to UTC ISO strings. Only internal storage uses short keys; public player `items` fields retain their existing names. The raw document, hashes, and private media paths are never returned.

Question/option text, answer keys, explanations, and media remain in the paper. The document retains exact presented order even if future shuffle implementation changes. An attempt-row lock protects all changes; batch validation precedes one update of responses and summary/version. Replays do not write; invalid batches roll back. Application checks replace per-answer SQL uniqueness constraints. Completion adds no extra full quiz snapshot.

## Manual deployment

For existing databases using equal-weight answer rows, back up and stop traffic/workers before running [attempt-responses-upgrade.sql](attempt-responses-upgrade.sql) in phpMyAdmin. This explicitly deletes all assessment attempts, their answers, and integrity events; it cannot preserve old tab credentials. It preserves users, authored content, papers/media, practice counters/keys, and first-start metadata. MySQL DDL implicitly commits, so keep activity stopped until the script and matching code deployment both finish. Do not rerun the upgrade.

Fresh databases import [schema.sql](schema.sql) only. The application does not automatically apply SQL or purge future results. Retention and archival policy are separate future work.

## Measured tradeoff (2026-09-27)

The isolated benchmark used MySQL 8.0.44 (the available local binary), InnoDB with a 128 MiB buffer pool and durable transaction commits, PHP 8.4.16, and 200 completed assessments per size. Four-choice questions alternate with short text every fifth question. Both formats retain full timing/retry/grading metadata. Table data plus indexes are measured after `ANALYZE TABLE`; unchanged identity/policy/report columns are excluded from both sides. The legacy side includes the answer table (including its foreign-key supporting index) and a minimal summary row; JSON includes a minimal summary row and response document.

| Questions per attempt | Legacy data + indexes | Response JSON data + indexes | Mean binary JSON/attempt | Legacy update median | JSON update median |
| --- | ---: | ---: | ---: | ---: | ---: |
| 20 | 2,473,984 bytes | 3,686,400 bytes | 8,301 bytes | 1.474 ms | 4.409 ms |
| 100 | 13,172,736 bytes | 9,977,856 bytes | 41,382 bytes | 1.893 ms | 14.276 ms |
| 500 | 50,003,968 bytes | 46,678,016 bytes | 208,195 bytes | 3.957 ms | 62.894 ms |

These are local microbenchmarks, not throughput guarantees. Fifty repeated one-item persistence updates include row locking, reads, JSON validation/encoding for the new format, writes, and commits; they exclude common HTTP, grading, and paper-loading costs. JSON sends the entire document (about 7.4/37.1/186 KB in these samples) on each changed batch. Page allocation and off-page JSON storage make results size-dependent: this sample saves about 24% at 100 questions and 7% at 500, but uses about 49% more space at 20 questions. Fewer rows are not a general storage or write-performance win. Debounced/batched saves remain important; no compression or partial-JSON update optimization is claimed.

Reproduce with `php tests/mysql/attempt-responses.php /private/tmp/edutest-responses-mysql-XXXXXX/mysql.sock` on a separately initialized temporary server. The helper verifies the actual data directory before creating disposable databases. It also compares upgraded/fresh DDL, checks preserved records, and exercises conflicting drafts and duplicate finalization from separate PHP processes. The fixture `tests/fixtures/schema-before-attempt-responses.sql` exists only to test the old layout/upgrade, not for installation. MySQL 8.4 deployment verification remains required.
