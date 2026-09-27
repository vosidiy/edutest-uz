<?php

declare(strict_types=1);

// Standalone, opt-in check against a dedicated temporary MySQL server only.
// php tests/mysql/attempt-responses.php /private/tmp/edutest-responses-mysql-XXXXXX/mysql.sock
use App\Services\Player\AttemptResponses;
use App\Services\Player\PlayerRuntime;
use App\Services\MediaService;
use App\Services\QuizAuthoringService;
use App\Services\QuizPaperService;
use Tests\Support\IsolatedMysql;

chdir(dirname(__DIR__, 2));
require 'vendor/codeigniter4/framework/system/util_bootstrap.php';

function check(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

function applySql($db, string $sql): void
{
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $db->query(trim($statement));
}

function ddl($db): array
{
    $tables = $db->listTables(); sort($tables);
    $result = [];
    foreach ($tables as $table) {
        $value = $db->query('SHOW CREATE TABLE `' . $table . '`')->getRowArray()['Create Table'];
        $result[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $value);
    }
    return $result;
}

// Used by independent workers to exercise real service locking, not simulated conflicts.
if (($argv[1] ?? '') === '--sync-worker') {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    check((bool) preg_match('~^/private/tmp/edutest-responses-mysql-[a-zA-Z0-9]+/mysql.sock$~D', $input['socket']), 'Not an isolated socket');
    check((bool) preg_match('/^edutest_test_[a-f0-9]{16}$/D', $input['database']), 'Not an isolated database');
    $db = \Config\Database::connect(['DBDriver' => 'MySQLi', 'hostname' => $input['socket'], 'username' => 'root',
        'password' => '', 'database' => $input['database'], 'DBDebug' => true, 'DBPrefix' => '',
        'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_0900_ai_ci'], false);
    check(realpath($db->query('SELECT @@datadir AS dir')->getRow('dir')) === dirname($input['socket']) . '/data', 'Unexpected data directory');
    $db->query("SET time_zone = '+00:00'");
    config('Encryption')->key = 'isolated-response-benchmark-key';
    $player = new PlayerRuntime($db);
    try {
        $saved = $player->assessment->sync($input['attemptId'], $input['credential'], $input['payload']);
        echo json_encode(['ok' => true, 'version' => $saved['version']]);
    } catch (\App\Exceptions\PlayerException $error) {
        echo json_encode(['ok' => false, 'code' => $error->errorCode]);
    }
    exit;
}

$socket = $argv[1] ?? '';
$fresh = new IsolatedMysql($socket);
$old = new IsolatedMysql($socket);
$db = $fresh->connect();
$legacyDb = $old->connect();
try {
    applySql($db, file_get_contents('docs/schema.sql'));
    applySql($legacyDb, file_get_contents('tests/fixtures/schema-before-attempt-responses.sql'));
    $now = '2026-01-01 00:00:00.000000';
    $seed = function ($conn, bool $legacy) use ($now): void {
        $conn->table('users')->insert(['id' => 1, 'email' => 'fixture@example.test', 'password_hash' => 'unused', 'display_name' => 'Teacher', 'created_at' => $now, 'updated_at' => $now]);
        $conn->table('quizzes')->insert(['id' => 1, 'user_id' => 1, 'public_id' => str_repeat('a', 32), 'share_token' => str_repeat('b', 64),
            'title' => 'Preserved quiz', 'description' => '', 'instructions' => '', 'first_started_at' => $now, 'practice_starts' => 7, 'created_at' => $now, 'updated_at' => $now]);
        $conn->table('questions')->insert(['id' => 1, 'quiz_id' => 1, 'pos' => 1, 'type' => 'single_choice', 'content' => 'Preserved question', 'created_at' => $now, 'updated_at' => $now]);
        $conn->table('question_options')->insert(['question_id' => 1, 'pos' => 1, 'code' => str_repeat('c', 16), 'content' => 'Preserved choice', 'created_at' => $now, 'updated_at' => $now]);
        $conn->table('quiz_papers')->insert(['id' => 1, 'quiz_id' => 1, 'public_id' => str_repeat('d', 32), 'revision' => 1, 'definition' => '{}', 'created_at' => $now]);
        $conn->table('practice_keys')->insert(['quiz_id' => 1, 'paper_id' => 1, 'request_key' => str_repeat('e', 32), 'expires_at' => '2099-01-01']);
        $attempt = ['id' => 1, 'quiz_id' => 1, 'paper_id' => 1, 'public_id' => str_repeat('f', 32), 'token_hash' => str_repeat('t', 32),
            'start_key' => str_repeat('a', 32), 'start_hash' => str_repeat('h', 32), 'name' => 'Fixture', 'settings' => '{}', 'started_at' => $now, 'max_score' => '1.00', 'updated_at' => $now];
        if (! $legacy) $attempt['responses'] = AttemptResponses::encode([]);
        $conn->table('attempts')->insert($attempt);
        $conn->table('cheat_events')->insert(['attempt_id' => 1, 'event_key' => str_repeat('b', 32), 'type' => 'tab_hidden', 'received_at' => $now, 'data' => '{}']);
        if ($legacy) $conn->table('attempt_items')->insert(['attempt_id' => 1, 'quiz_id' => 1, 'question_id' => 1, 'pos' => 1, 'choice_order' => '[]']);
    };
    $seed($legacyDb, true);
    $preserved = [];
    foreach (['users', 'quizzes', 'questions', 'question_options', 'quiz_papers', 'practice_keys'] as $table) $preserved[$table] = $legacyDb->table($table)->get()->getResultArray();
    applySql($legacyDb, file_get_contents('docs/attempt-responses-upgrade.sql'));
    applySql($legacyDb, file_get_contents('docs/short-codes-integrity-upgrade.sql'));
    check(ddl($db) === ddl($legacyDb), 'Upgrade differs from the canonical schema');
    check(! $legacyDb->tableExists('attempt_items'), 'Legacy table survived');
    foreach (['attempts', 'cheat_events'] as $table) check($legacyDb->table($table)->countAllResults() === 0, 'Disposable rows survived');
    foreach ($preserved as $table => $rows) check($rows === $legacyDb->table($table)->get()->getResultArray(), 'Upgrade changed ' . $table);
    echo "Fresh schema and destructive upgrade match; preserved records are unchanged.\n";

    // Test NOT NULL/object checks through the fresh schema.
    $seed($db, false);
    foreach (['[]', 'null', '"text"'] as $invalid) {
        try { $db->table('attempts')->where('id', 1)->update(['responses' => $invalid]); throw new RuntimeException('Invalid JSON root accepted'); }
        catch (\CodeIgniter\Database\Exceptions\DatabaseException) {}
    }

    // Two workers submit from the same version while a parent holds the row lock.
    config('Encryption')->key = 'isolated-response-benchmark-key';
    $media = new MediaService($db);
    $papers = new QuizPaperService($db, $media);
    $author = new QuizAuthoringService($db, $media, $papers);
    $player = new PlayerRuntime($db, $media, $papers);
    $quiz = $author->create(1, 'Concurrency fixture', 'assessment');
    $doc = $author->document(1, $quiz['publicId']);
    $doc['questions'] = [['id' => null, 'type' => 'single_choice', 'content' => 'Choose', 'textAnswers' => [],
        'options' => [['id' => null, 'content' => 'Right', 'isCorrect' => true], ['id' => null, 'content' => 'Wrong', 'isCorrect' => false]]]];
    $author->save(1, $quiz['publicId'], $doc);
    $published = $author->transition(1, $quiz['publicId'], 'publish')['quiz'];
    $ticket = $player->admission->ticket(basename($published['shareUrl']));
    $attempt = $player->admission->start(['ticket' => $ticket['ticket'], 'name' => 'Race']);
    $loaded = $player->assessment->load($attempt['attemptId'], $attempt['credential']);
    $answer = ['questionId' => $loaded['quiz']['questions'][0]['id'], 'answerCodes' => [$loaded['quiz']['questions'][0]['correctCodes'][0]],
        'textAnswer' => '', 'startedAt' => $loaded['startedAt'], 'saveVer' => 1, 'submitKey' => str_repeat('1', 32), 'reason' => 'answered'];
    $race = function (array $payloads) use ($db, $fresh, $attempt): array {
        $db->transBegin();
        $db->query('SELECT id FROM attempts WHERE public_id = ? FOR UPDATE', [$attempt['attemptId']]);
        $workers = [];
        foreach ($payloads as $payload) {
            $process = proc_open([PHP_BINARY, __FILE__, '--sync-worker'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            fwrite($pipes[0], json_encode(['socket' => $fresh->socket, 'database' => $fresh->database, 'payload' => $payload] + $attempt, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        usleep(250000);
        $db->transCommit();
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            check(proc_close($process) === 0, 'Worker failed: ' . $err . $out);
            $results[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    };
    $draft = $answer; $draft['submitKey'] = null;
    $other = $draft; $other['answerCodes'] = [];
    $results = $race([['version' => 1, 'items' => [$draft]], ['version' => 1, 'items' => [$other]]]);
    check(count(array_filter($results, fn ($r) => $r['ok'])) === 1, 'Concurrent drafts both committed');
    check(count(array_filter($results, fn ($r) => ($r['code'] ?? null) === 'progress_conflict')) === 1, 'Missing concurrent conflict');
    $answer['saveVer'] = 2;
    $payload = ['version' => 2, 'items' => [$answer], 'finishReason' => 'completed'];
    $results = $race([$payload, $payload]);
    check($results[0] === ['ok' => true, 'version' => 3] && $results[1] === $results[0], 'Concurrent retry was not idempotent');
    echo "Concurrent draft conflict and duplicate finalization passed using separate processes.\n";

    // Compare the answer storage only; unchanged identity/settings/score columns are excluded.
    preg_match('/CREATE TABLE attempt_items \((.*?)\) ENGINE=InnoDB.*?;/s', file_get_contents('tests/fixtures/schema-before-attempt-responses.sql'), $match);
    // Retain the FK's automatically created supporting index even though this benchmark
    // uses minimal summary rows without quiz/paper data and therefore omits the FK itself.
    $legacyBody = preg_replace('/  CONSTRAINT fk_attempt_items_attempt\s+FOREIGN KEY \(attempt_id, quiz_id\) REFERENCES attempts\(id, quiz_id\),\n/',
        "  INDEX fk_attempt_items_attempt (attempt_id, quiz_id),\n", $match[1]);
    $db->query("SET SESSION information_schema_stats_expiry = 0");
    $measurements = [];
    foreach ([20, 100, 500] as $count) {
        $db->query('CREATE TABLE legacy_items (' . $legacyBody . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
        $db->query('CREATE TABLE legacy_summary (id BIGINT UNSIGNED PRIMARY KEY, version INT UNSIGNED NOT NULL) ENGINE=InnoDB');
        $db->query('CREATE TABLE json_summary (id BIGINT UNSIGNED PRIMARY KEY, version INT UNSIGNED NOT NULL, responses JSON NOT NULL) ENGINE=InnoDB');
        $sample = [];
        for ($a = 1; $a <= 200; $a++) {
            $rows = [];
            for ($q = 1; $q <= $count; $q++) {
                $codes = array_map(fn ($i) => substr(hash('sha256', "choice:$q:$i"), 0, 16), range(1, 4));
                $text = $q % 5 === 0;
                $rows[] = ['attempt_id' => $a, 'quiz_id' => 1, 'question_id' => (string) $q, 'pos' => $q,
                    'choice_order' => json_encode($text ? [] : $codes), 'answer_codes' => json_encode($text ? [] : [$codes[0]]),
                    'text_answer' => $text ? 'Tashkent response ' . $a : null, 'status' => 'locked',
                    'started_at' => $now, 'locked_at' => '2026-01-01 00:00:20.000000', 'saved_at' => '2026-01-01 00:00:20.000000',
                    'lock_reason' => 'answered', 'save_ver' => 2, 'submit_key' => substr(hash('sha256', "$a:$q:key"), 0, 32),
                    'submit_hash' => hash('sha256', "$a:$q:answer"), 'result' => 'correct', 'credit' => '1.00'];
            }
            $db->transBegin();
            $db->table('legacy_summary')->insert(['id' => $a, 'version' => 1]);
            $legacyRows = array_map(function ($row) { $row['submit_hash'] = \App\Services\Player\PlayerStore::binary(hex2bin($row['submit_hash'])); return $row; }, $rows);
            $db->table('legacy_items')->insertBatch($legacyRows);
            $db->table('json_summary')->insert(['id' => $a, 'version' => 1, 'responses' => AttemptResponses::encode($rows)]);
            $db->transCommit();
            if ($a === 1) $sample = $rows;
        }
        foreach (['legacy_items', 'legacy_summary', 'json_summary'] as $table) $db->query('ANALYZE TABLE ' . $table);
        $sizes = [];
        foreach ($db->query('SELECT TABLE_NAME, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?,?,?)', [$fresh->database, 'legacy_items', 'legacy_summary', 'json_summary'])->getResultArray() as $row) $sizes[$row['TABLE_NAME']] = (int) $row['bytes'];
        $jsonBytes = (int) $db->query('SELECT AVG(JSON_STORAGE_SIZE(responses)) AS bytes FROM json_summary')->getRow('bytes');
        $timings = [];
        foreach (['legacy', 'json'] as $mode) {
            $elapsed = [];
            for ($i = 0; $i < 50; $i++) {
                $start = hrtime(true);
                $db->transBegin();
                if ($mode === 'legacy') {
                    $db->query('SELECT version FROM legacy_summary WHERE id = 1 FOR UPDATE');
                    $db->table('legacy_items')->where('attempt_id', 1)->orderBy('pos')->get()->getResultArray();
                    $db->table('legacy_items')->where('attempt_id', 1)->where('pos', $count)->update(['text_answer' => 'Changed ' . $i, 'save_ver' => $i + 3]);
                    $db->query('UPDATE legacy_summary SET version = version + 1 WHERE id = 1');
                } else {
                    $stored = $db->query('SELECT responses FROM json_summary WHERE id = 1 FOR UPDATE')->getRow('responses');
                    $rows = AttemptResponses::decode($stored);
                    $rows[$count - 1]['text_answer'] = 'Changed ' . $i;
                    $rows[$count - 1]['save_ver'] = $i + 3;
                    $db->query('UPDATE json_summary SET responses = ?, version = version + 1 WHERE id = 1', [AttemptResponses::encode($rows)]);
                }
                $db->transCommit();
                $elapsed[] = (hrtime(true) - $start) / 1e6;
            }
            sort($elapsed);
            $timings[$mode . 'MedianMs'] = round($elapsed[25], 3);
            $timings[$mode . 'P95Ms'] = round($elapsed[47], 3);
        }
        $measurements[] = ['questions' => $count, 'attempts' => 200,
            'legacyBytes' => $sizes['legacy_items'] + $sizes['legacy_summary'], 'jsonBytes' => $sizes['json_summary'],
            'meanJsonDocumentBytes' => $jsonBytes, 'wireJsonDocumentBytes' => strlen(AttemptResponses::encode($sample))] + $timings;
        foreach (['legacy_items', 'legacy_summary', 'json_summary'] as $table) $db->query('DROP TABLE ' . $table);
    }
    echo json_encode(['mysql' => $db->query('SELECT VERSION() AS v')->getRow('v'), 'measurements' => $measurements], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} finally {
    $db->close(); $legacyDb->close();
    $fresh->close(); $old->close();
}
