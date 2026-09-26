<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\MediaService;
use App\Services\Player\PlayerRuntime;
use App\Services\QuizAuthoringService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** Fresh in-memory test database only. Never resolves the default/MAMP connection. */
abstract class PlayerTestCase extends CIUnitTestCase
{
    protected PlayerRuntime $player;
    protected QuizAuthoringService $authoring;
    protected MediaService $media;
    protected string $mediaRoot;
    private string $previousKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousKey = (string) config('Encryption')->key;
        config('Encryption')->key = 'isolated-player-fixture-key-not-for-deployment';
        $this->db = Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => 'player_', 'DBDebug' => true, 'foreignKeys' => true], false);
        $this->db->initialize();
        $schema = file_get_contents(ROOTPATH . 'docs/schema.sql');
        preg_match_all('/CREATE TABLE (\w+) \((.*?)\) ENGINE=InnoDB.*?;/s', $schema, $tables, PREG_SET_ORDER);
        foreach ($tables as $table) {
            $body = preg_replace('/^\s*INDEX [^\n]*\n/m', '', $table[2]);
            $body = str_replace('BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $body);
            $body = preg_replace('/\b(?:BIGINT|SMALLINT|INT) UNSIGNED\b/', 'INTEGER', $body);
            $body = preg_replace('/\bTINYINT\(1\)/', 'INTEGER', $body);
            $body = preg_replace('/\bDATETIME\(6\)/', 'TEXT', $body);
            $body = preg_replace('/\bBINARY\(\d+\)/', 'BLOB', $body);
            $body = preg_replace('/\s+(?:CHARACTER SET|COLLATE) [a-zA-Z0-9_]+/', '', $body);
            $body = preg_replace('/REFERENCES (\w+)/', 'REFERENCES player_$1', $body);
            $this->db->query('CREATE TABLE player_' . $table[1] . ' (' . $body . ')');
        }
        $this->mediaRoot = WRITEPATH . 'testing/player-' . bin2hex(random_bytes(6));
        $this->media = new MediaService($this->db, $this->mediaRoot);
        $this->authoring = new QuizAuthoringService($this->db, $this->media);
        $this->player = new PlayerRuntime($this->db, $this->media);
    }

    protected function tearDown(): void
    {
        $this->db->close();
        config('Encryption')->key = $this->previousKey;
        if (is_dir($this->mediaRoot)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->mediaRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
            rmdir($this->mediaRoot);
        }
        parent::tearDown();
    }

    protected function quiz(string $mode = 'assessment', array $settings = [], int $count = 2): array
    {
        $now = '2026-01-01 00:00:00.000000';
        $this->db->table('users')->insert(['email' => bin2hex(random_bytes(4)) . '@example.test', 'password_hash' => 'unused', 'display_name' => 'Quiz Teacher', 'created_at' => $now, 'updated_at' => $now]);
        $owner = (int) $this->db->insertID();
        $created = $this->authoring->create($owner, 'Capital cities', $mode);
        $doc = array_replace($this->authoring->document($owner, $created['publicId']), $settings);
        $doc['questions'] = [];
        for ($i = 0; $i < $count; $i++) {
            $doc['questions'][] = ['id' => null, 'type' => 'single_choice', 'content' => 'Capital of France? ' . $i, 'points' => '2.50',
                'timeLimitSec' => null, 'explanation' => 'Paris is the capital of France.', 'textAnswers' => [],
                'options' => [['id' => null, 'content' => 'Berlin', 'isCorrect' => false], ['id' => null, 'content' => 'Paris', 'isCorrect' => true]]];
        }
        $this->authoring->save($owner, $created['publicId'], $doc);
        $published = $this->authoring->transition($owner, $created['publicId'], 'publish')['quiz'];
        return ['owner' => $owner, 'publicId' => $created['publicId'], 'share' => basename($published['shareUrl']), 'document' => $published];
    }

    protected function startQuiz(array $quiz, array $identity = []): array
    {
        $ticket = $this->player->admission->ticket($quiz['share']);
        $data = $this->player->admission->start(['ticket' => $ticket['ticket']] + $identity + ($ticket['mode'] === 'assessment' ? ['name' => 'Student'] : []));
        return $data['mode'] === 'assessment' ? $data + $this->player->assessment->load($data['attemptId'], $data['credential']) : $data;
    }

    protected function submission(array $attempt, int $index, bool $correct = true): array
    {
        $question = $attempt['quiz']['questions'][$index];
        return ['questionId' => $question['id'], 'answerCodes' => [$correct ? $question['correctCodes'][0] : $question['options'][0]['code']],
            'textAnswer' => '', 'saveVer' => 1, 'submitKey' => bin2hex(random_bytes(16)), 'reason' => 'answered', 'startedAt' => $attempt['startedAt']];
    }
}
