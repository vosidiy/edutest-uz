<?php

declare(strict_types=1);

namespace App\Services\Player;

use RuntimeException;

/**
 * Server-owned response storage; short keys avoid repeating long names for every answer.
 * q=question ID, o=choice order, a=answer codes; remaining keys are mapped in FIELDS.
 * Expanded rows are private to grading/reporting services. Public APIs stay unchanged.
 */
final class AttemptResponses
{
    private const FIELDS = [
        's' => ['status', 'pending'],
        'b' => ['started_at', null],
        'l' => ['locked_at', null],
        'r' => ['lock_reason', null],
        't' => ['text_answer', null],
        'v' => ['save_ver', 0],
        'u' => ['saved_at', null],
        'k' => ['submit_key', null],
        'h' => ['submit_hash', null],
        'g' => ['result', null],
        'c' => ['credit', null],
    ];

    public static function initialize(array $questions, string $startedAt): string
    {
        $items = [];
        foreach ($questions as $index => $question) {
            $items[] = [
                'question_id' => (string) $question['id'],
                'choice_order' => json_encode(array_column($question['options'], 'code'), JSON_THROW_ON_ERROR),
                'status' => $index === 0 ? 'active' : 'pending',
                'started_at' => $index === 0 ? $startedAt : null,
            ];
        }
        return self::encode($items);
    }

    /** Encode ordered internal rows, omitting default/null values and duplicate position fields. */
    public static function encode(array $rows): string
    {
        $items = [];
        foreach ($rows as $row) {
            $item = ['q' => (string) $row['question_id'],
                'o' => json_decode($row['choice_order'], true, 512, JSON_THROW_ON_ERROR)];
            $codes = json_decode($row['answer_codes'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
            if ($codes !== []) $item['a'] = $codes;
            foreach (self::FIELDS as $key => [$column, $default]) {
                $value = $row[$column] ?? $default;
                if ($value !== $default) $item[$key] = $value;
            }
            $items[] = $item;
        }
        $document = ['schemaVersion' => 1, 'items' => $items];
        self::decode($document); // Enforce the same invariants on every read and write.
        return json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return list<array<string, mixed>> */
    public static function decode(string|array $stored): array
    {
        $doc = is_string($stored) ? json_decode($stored, true, 512, JSON_THROW_ON_ERROR) : $stored;
        if (! is_array($doc) || ($doc['schemaVersion'] ?? null) !== 1
            || ! is_array($doc['items'] ?? null) || ! array_is_list($doc['items']) || count($doc['items']) > 60000
            || array_diff(array_keys($doc), ['schemaVersion', 'items']) !== []) self::invalid();
        $rows = [];
        $seen = [];
        $keys = [];
        foreach ($doc['items'] as $index => $item) {
            if (! is_array($item) || array_diff(array_keys($item), ['q', 'o', 'a', ...array_keys(self::FIELDS)]) !== []
                || ! is_string($item['q'] ?? null) || ! preg_match('/^[1-9][0-9]*$/D', $item['q'])
                || isset($seen[$item['q']])) self::invalid();
            $seen[$item['q']] = true;
            self::codes($item['o'] ?? null);
            self::codes(array_key_exists('a', $item) ? $item['a'] : []);
            if (array_diff($item['a'] ?? [], $item['o']) !== []) self::invalid();
            $row = ['question_id' => $item['q'], 'pos' => $index + 1,
                'choice_order' => json_encode($item['o'], JSON_THROW_ON_ERROR),
                'answer_codes' => json_encode($item['a'] ?? [], JSON_THROW_ON_ERROR)];
            foreach (self::FIELDS as $key => [$column, $default]) $row[$column] = array_key_exists($key, $item) ? $item[$key] : $default;
            if (! in_array($row['status'], ['pending', 'active', 'locked'], true)
                || ! is_int($row['save_ver']) || $row['save_ver'] < 0 || $row['save_ver'] > 4294967295
                || ($row['text_answer'] !== null && (! is_string($row['text_answer']) || mb_strlen($row['text_answer']) > 500))) self::invalid();
            foreach (['started_at', 'locked_at', 'saved_at'] as $field) {
                if ($row[$field] === null) continue;
                if (! is_string($row[$field]) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/D', $row[$field])) self::invalid();
                PlayerStore::date(str_replace(' ', 'T', $row[$field]) . 'Z');
            }
            if (($row['submit_key'] === null) !== ($row['submit_hash'] === null)) self::invalid();
            if ($row['submit_key'] !== null) {
                if (! is_string($row['submit_key']) || ! preg_match('/^[a-f0-9]{32}$/D', $row['submit_key'])
                    || ! is_string($row['submit_hash']) || ! preg_match('/^[a-f0-9]{64}$/D', $row['submit_hash'])
                    || isset($keys[$row['submit_key']])) self::invalid();
                $keys[$row['submit_key']] = true;
            }
            if ($row['status'] === 'locked') {
                if (! in_array($row['lock_reason'], ['answered', 'skipped', 'attempt_timeout'], true)
                    || ! in_array($row['result'], ['correct', 'partial', 'wrong', 'unanswered'], true)
                    || ! is_string($row['credit']) || ! preg_match('/^(?:0\.\d{2}|1\.00)$/D', $row['credit'])
                    || $row['locked_at'] === null) self::invalid();
                if ($row['lock_reason'] !== 'attempt_timeout' && ($row['submit_key'] === null
                    || $row['started_at'] === null || $row['saved_at'] === null)) self::invalid();
            } elseif ($row['result'] !== null || $row['credit'] !== null || $row['locked_at'] !== null
                || $row['lock_reason'] !== null || $row['submit_key'] !== null) self::invalid();
            if ($row['status'] === 'active' && $row['started_at'] === null) self::invalid();
            if ($row['status'] === 'pending' && ($row['started_at'] !== null || $row['saved_at'] !== null
                || $row['save_ver'] !== 0 || $row['text_answer'] !== null || ($item['a'] ?? []) !== [])) self::invalid();
            $rows[] = $row;
        }
        return $rows;
    }

    private static function codes(mixed $codes): void
    {
        if (! is_array($codes) || ! array_is_list($codes) || count($codes) > 60000) self::invalid();
        $seen = [];
        foreach ($codes as $code) {
            if (! is_string($code) || $code === '' || strlen($code) > 16 || isset($seen[$code])) self::invalid();
            $seen[$code] = true;
        }
    }

    private static function invalid(): never
    {
        throw new RuntimeException('The stored assessment responses are invalid.');
    }
}
