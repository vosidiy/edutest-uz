<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Services\Player\PlayerStore;
use Tests\Support\PlayerTestCase;

final class OfflineConcurrencyTest extends PlayerTestCase
{
    private function parallel(array $jobs): array
    {
        // Independent PHP processes/connections; never fork an inherited database socket.
        $code = <<<'PHP'
chdir($argv[1]);
require 'vendor/codeigniter4/framework/system/util_bootstrap.php';
$job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$root = realpath(dirname($job['socket']));
if (!preg_match('~^/private/tmp/edutest-responses-mysql-[a-zA-Z0-9]+$~D', $root)
    || !preg_match('/^edutest_test_[a-f0-9]{16}$/D', $job['database'])) throw new RuntimeException('Unsafe test database');
$db = \Config\Database::connect(['DBDriver'=>'MySQLi', 'hostname'=>$job['socket'], 'username'=>'root', 'password'=>'',
    'database'=>$job['database'], 'DBPrefix'=>'', 'DBDebug'=>true, 'charset'=>'utf8mb4', 'DBCollat'=>'utf8mb4_0900_ai_ci'], false);
$db->initialize();
if (realpath($db->query('SELECT @@datadir AS dir')->getRow('dir')) !== $root . '/data') throw new RuntimeException('Unsafe datadir');
config('Encryption')->key = 'isolated-player-fixture-key-not-for-deployment';
$service = (new \App\Services\Player\PlayerRuntime($db))->assessment;
try {
    $a=$job['attempt'];
    $result=match ($job['action']) {
        'answer'=>$service->answer($a['attemptId'], $a['items'][0]['questionId'], $a['credential'], $job['input']),
        'finish'=>$service->finish($a['attemptId'], $a['credential'], $job['input']),
        'load'=>$service->load($a['attemptId'], $a['credential']),
    };
    echo json_encode(['data'=>$result], JSON_THROW_ON_ERROR);
} catch (\App\Exceptions\PlayerException $error) { echo json_encode(['error'=>$error->errorCode]); }
$db->close();
PHP;
        $children = [];
        foreach ($jobs as $job) {
            $process = proc_open([PHP_BINARY, '-r', $code, ROOTPATH], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode($job + ['socket' => getenv('EDUTEST_TEST_MYSQL_SOCKET'), 'database' => $this->db->database], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $children[] = [$process, $pipes];
        }
        $results = [];
        foreach ($children as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error . $output);
            $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        }
        return $results;
    }

    public function testConcurrentExpiryAnswerRetriesAndFinishNeverLoseConfirmedWork(): void
    {
        if (!getenv('EDUTEST_TEST_MYSQL_SOCKET')) self::markTestSkipped('Requires isolated MySQL.');
        $a = $this->startQuiz($this->quiz(settings: ['timeLimitMinutes'=>'0.5'], count: 1));
        $start = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-120 seconds')->format('Y-m-d H:i:s.u');
        $this->db->table('attempts')->where('public_id', $a['attemptId'])->update(['started_at'=>$start,
            'last_activity_at'=>$start, 'client_activity_at'=>$start, 'expires_at'=>PlayerStore::due($start,30)]);
        $input = $this->confirmation($this->submission($a,0), PlayerStore::iso(PlayerStore::due($start,5)));
        $job = ['action'=>'answer','attempt'=>$a,'input'=>$input];
        $results = $this->parallel([$job, $job, ['action'=>'load','attempt'=>$a]]);
        self::assertArrayHasKey('data', $results[0]); self::assertArrayHasKey('data', $results[1]);
        self::assertSame(1,$this->db->table('attempt_answers')->where('status','answered')->countAllResults());
        $finish = ['action'=>'finish','attempt'=>$a,'input'=>$this->finishBody($a,at:PlayerStore::iso(PlayerStore::due($start,10)))];
        $results = $this->parallel([$finish, $finish, $job, ['action'=>'load','attempt'=>$a]]);
        foreach ($results as $result) self::assertArrayHasKey('data',$result);
        $done = $this->player->assessment->load($a['attemptId'],$a['credential']);
        self::assertSame('completed',$done['status']); self::assertSame('1',$done['result']['score']); self::assertTrue($done['lateSync']);
    }
}
