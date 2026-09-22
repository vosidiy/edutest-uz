<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\AuthoringException;
use App\Services\MediaService;
use App\Services\QuizAuthoringService;
use App\Services\TeacherQueryService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class QuizAuthoringServiceTest extends CIUnitTestCase
{
    private BaseConnection $authoringDb;
    private Forge $forge;
    private QuizAuthoringService $authoring;
    private MediaService $media;
    private string $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authoringDb = Database::connect('tests');
        $this->forge = Database::forge('tests');
        $this->dropTables();
        $this->createTables();
        $this->mediaRoot = WRITEPATH . 'testing/quiz-media-' . bin2hex(random_bytes(5));
        $this->media = new MediaService($this->authoringDb, $this->mediaRoot);
        $this->authoring = new QuizAuthoringService($this->authoringDb, $this->media);
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        $this->removeDirectory($this->mediaRoot);
        parent::tearDown();
    }

    public function testCreateSavePublishAndLifecycle(): void
    {
        $owner = $this->insertUser('owner@example.test');
        $created = $this->authoring->create($owner, 'Biology review', 'assessment');
        $document = $this->authoring->document($owner, $created['publicId']);

        $this->assertSame('draft', $document['status']);
        $this->assertSame(1, $document['version']);
        $this->assertSame([], $document['questions']);

        $document['questions'] = [[
            'id' => null,
            'type' => 'single_choice',
            'content' => 'Which structure contains DNA?',
            'explanation' => 'The nucleus contains chromosomes.',
            'points' => '2.50',
            'timeLimitSec' => 30,
            'textAnswers' => [],
            'options' => [
                ['id' => null, 'content' => 'Nucleus', 'isCorrect' => true],
                ['id' => null, 'content' => 'Cell wall', 'isCorrect' => false],
            ],
        ]];
        $saved = $this->authoring->save($owner, $created['publicId'], $document);

        $this->assertSame(2, $saved['version']);
        $this->assertSame('2.50', $saved['questions'][0]['points']);
        $this->assertNotSame('', $saved['questions'][0]['id']);
        $this->assertCount(2, $saved['questions'][0]['options']);
        $this->assertNotSame('', $saved['questions'][0]['options'][0]['code']);

        $firstQuestion = $saved['questions'][0];
        $saved['questions'] = [[
            'id' => null,
            'type' => 'short_text',
            'content' => 'Name the process.',
            'explanation' => '',
            'points' => '1.00',
            'timeLimitSec' => null,
            'textAnswers' => ['photosynthesis'],
            'options' => [],
        ], $firstQuestion];
        $reordered = $this->authoring->save($owner, $created['publicId'], $saved);
        $this->assertSame('Name the process.', $reordered['questions'][0]['content']);
        $this->assertSame($firstQuestion['id'], $reordered['questions'][1]['id']);

        try {
            $this->authoring->save($owner, $created['publicId'], $document);
            $this->fail('A stale version should fail.');
        } catch (AuthoringException $exception) {
            $this->assertSame('version_conflict', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }

        $published = $this->authoring->transition($owner, $created['publicId'], 'publish')['quiz'];
        $this->assertSame('published', $published['status']);
        $this->assertNotNull($this->authoring->publicSummary(basename($published['shareUrl'])));

        $duplicate = $this->authoring->duplicate($owner, $created['publicId']);
        $duplicateDocument = $this->authoring->document($owner, $duplicate['publicId']);
        $this->assertSame('draft', $duplicateDocument['status']);
        $this->assertFalse($duplicateDocument['listed']);
        $this->assertCount(2, $duplicateDocument['questions']);

        $queries = new TeacherQueryService($this->authoringDb);
        $this->assertSame(2, $queries->library($owner, [], 'active')['pagination']['total']);
        $this->assertSame(2, $queries->dashboard($owner)['metrics']['totalQuizzes']);

        $this->assertSame('closed', $this->authoring->transition($owner, $created['publicId'], 'close')['quiz']['status']);
        $this->assertSame('published', $this->authoring->transition($owner, $created['publicId'], 'reopen')['quiz']['status']);
        $this->assertSame('archived', $this->authoring->transition($owner, $created['publicId'], 'archive')['quiz']['status']);
        $this->assertSame('draft', $this->authoring->transition($owner, $created['publicId'], 'unarchive')['quiz']['status']);
        $this->assertTrue($this->authoring->transition($owner, $created['publicId'], 'trash')['quiz']['deleted']);
        $this->assertFalse($this->authoring->transition($owner, $created['publicId'], 'restore')['quiz']['deleted']);
    }

    public function testPracticeSaveClearsAssessmentOnlySettings(): void
    {
        $owner = $this->insertUser('practice@example.test');
        $created = $this->authoring->create($owner, 'Private practice', 'assessment');
        $document = $this->authoring->document($owner, $created['publicId']);
        $document['mode'] = 'practice';
        $document['emailMode'] = 'required';
        $document['phoneMode'] = 'optional';
        $document['cheatCheck'] = true;
        $document['passcode'] = ['action' => 'set', 'value' => 'secret12'];

        $saved = $this->authoring->save($owner, $created['publicId'], $document);

        $this->assertSame('practice', $saved['mode']);
        $this->assertSame('hidden', $saved['emailMode']);
        $this->assertSame('hidden', $saved['phoneMode']);
        $this->assertFalse($saved['cheatCheck']);
        $this->assertFalse($saved['passcode']['configured']);
    }

    public function testIncompleteDraftCannotPublish(): void
    {
        $owner = $this->insertUser('incomplete@example.test');
        $created = $this->authoring->create($owner, 'Incomplete quiz', 'assessment');

        try {
            $this->authoring->transition($owner, $created['publicId'], 'publish');
            $this->fail('An empty quiz should not publish.');
        } catch (AuthoringException $exception) {
            $this->assertSame('not_publishable', $exception->errorCode);
            $this->assertArrayHasKey('questions', $exception->fields);
        }
    }

    public function testOwnershipAndFrozenContentAreEnforced(): void
    {
        $owner = $this->insertUser('owner2@example.test');
        $other = $this->insertUser('other@example.test');
        $created = $this->authoring->create($owner, 'Frozen quiz', 'assessment');

        try {
            $this->authoring->document($other, $created['publicId']);
            $this->fail('A non-owner should not load the quiz.');
        } catch (AuthoringException $exception) {
            $this->assertSame(404, $exception->status);
        }

        $this->authoringDb->table('quizzes')->where('public_id', $created['publicId'])->update([
            'frozen_at' => '2026-01-01 00:00:00',
        ]);
        $document = $this->authoring->document($owner, $created['publicId']);
        $document['title'] = 'Changed title';

        try {
            $this->authoring->save($owner, $created['publicId'], $document);
            $this->fail('Frozen authored content should not change.');
        } catch (AuthoringException $exception) {
            $this->assertSame('quiz_frozen', $exception->errorCode);
        }

        $document = $this->authoring->document($owner, $created['publicId']);
        $document['listed'] = true;
        $saved = $this->authoring->save($owner, $created['publicId'], $document);
        $this->assertTrue($saved['listed']);
    }

    public function testPrivateImageAndValidatedVideoMedia(): void
    {
        config('Encryption')->key = 'authoring-test-signing-key';
        $owner = $this->insertUser('media@example.test');
        $created = $this->authoring->create($owner, 'Media quiz', 'assessment');
        $document = $this->authoring->document($owner, $created['publicId']);
        $document['questions'] = [[
            'id' => null, 'type' => 'single_choice', 'content' => 'Media question',
            'explanation' => '', 'points' => '1.00', 'timeLimitSec' => null, 'textAnswers' => [],
            'options' => [
                ['id' => null, 'content' => 'A', 'isCorrect' => true],
                ['id' => null, 'content' => 'B', 'isCorrect' => false],
            ],
        ]];
        $saved = $this->authoring->save($owner, $created['publicId'], $document);
        $questionId = (int) $saved['questions'][0]['id'];

        $temp = tempnam(sys_get_temp_dir(), 'edutest-image-');
        $this->assertNotFalse($temp);
        file_put_contents($temp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        $upload = new TestUploadedFile($temp, 'pixel.png', 'image/png', filesize($temp), UPLOAD_ERR_OK);
        $attached = $this->media->attachFile($owner, $created['publicId'], 'question', $questionId, $upload, $saved['version']);

        $this->assertSame('image', $attached['media']['type']);
        $this->assertStringNotContainsString('quiz-media', $attached['media']['url']);
        $token = basename((string) parse_url($attached['media']['url'], PHP_URL_PATH));
        $resolved = $this->media->resolveSigned($token);
        $this->assertFileExists($resolved['path']);
        $this->assertSame('image/png', $resolved['mime']);

        try {
            $this->media->resolveSigned($token . 'tampered');
            $this->fail('A tampered media token should fail.');
        } catch (AuthoringException $exception) {
            $this->assertSame(404, $exception->status);
        }

        $waveTemp = tempnam(sys_get_temp_dir(), 'edutest-audio-');
        $this->assertNotFalse($waveTemp);
        $wave = 'RIFF' . pack('V', 36) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8) . 'data' . pack('V', 0);
        file_put_contents($waveTemp, $wave);
        $waveUpload = new TestUploadedFile($waveTemp, 'sample.wav', 'audio/wav', filesize($waveTemp), UPLOAD_ERR_OK);
        $audio = $this->media->attachFile($owner, $created['publicId'], 'question', $questionId, $waveUpload, $attached['version']);
        $this->assertSame('audio', $audio['media']['type']);
        $this->assertFileDoesNotExist($resolved['path']);
        $audioToken = basename((string) parse_url($audio['media']['url'], PHP_URL_PATH));
        $originalAudio = $this->media->resolveSigned($audioToken);

        $duplicate = $this->authoring->duplicate($owner, $created['publicId']);
        $duplicateDocument = $this->authoring->document($owner, $duplicate['publicId']);
        $duplicateToken = basename((string) parse_url($duplicateDocument['questions'][0]['media']['url'], PHP_URL_PATH));
        $duplicateMedia = $this->media->resolveSigned($duplicateToken);
        $this->assertFileExists($duplicateMedia['path']);
        $this->assertNotSame($duplicateMedia['path'], $originalAudio['path']);

        $fakeTemp = tempnam(sys_get_temp_dir(), 'edutest-fake-');
        file_put_contents($fakeTemp, '<?php echo "not media";');
        try {
            $this->media->attachFile(
                $owner,
                $created['publicId'],
                'question',
                $questionId,
                new TestUploadedFile($fakeTemp, 'fake.jpg', 'image/jpeg', filesize($fakeTemp), UPLOAD_ERR_OK),
                $audio['version'],
            );
            $this->fail('A disguised file should be rejected.');
        } catch (AuthoringException $exception) {
            $this->assertSame('unsupported_media', $exception->errorCode);
        } finally {
            @unlink($fakeTemp);
        }

        $largeTemp = tempnam(sys_get_temp_dir(), 'edutest-large-');
        file_put_contents($largeTemp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        $largeHandle = fopen($largeTemp, 'ab');
        ftruncate($largeHandle, (5 * 1024 * 1024) + 1);
        fclose($largeHandle);
        try {
            $this->media->attachFile(
                $owner,
                $created['publicId'],
                'question',
                $questionId,
                new TestUploadedFile($largeTemp, 'large.png', 'image/png', filesize($largeTemp), UPLOAD_ERR_OK),
                $audio['version'],
            );
            $this->fail('An oversized image should be rejected.');
        } catch (AuthoringException $exception) {
            $this->assertSame('media_too_large', $exception->errorCode);
        } finally {
            @unlink($largeTemp);
        }

        $video = $this->media->attachVideo(
            $owner,
            $created['publicId'],
            'question',
            $questionId,
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            $audio['version'],
        );
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $video['media']['embedUrl']);
        $this->assertFileDoesNotExist($originalAudio['path']);
        $this->assertFileExists($duplicateMedia['path']);

        $this->expectException(AuthoringException::class);
        $this->media->attachVideo(
            $owner,
            $created['publicId'],
            'question',
            $questionId,
            'http://example.com/video.mp4',
            $video['version'],
        );
    }

    private function insertUser(string $email): int
    {
        $now = '2026-01-01 00:00:00';
        $this->authoringDb->table('users')->insert([
            'email' => $email,
            'password_hash' => password_hash('secret1', PASSWORD_DEFAULT),
            'display_name' => 'Test Teacher',
            'timezone' => 'Asia/Tashkent',
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->authoringDb->insertID();
    }

    private function createTables(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'auto_increment' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 254],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'display_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'timezone' => ['type' => 'VARCHAR', 'constraint' => 64],
            'active' => ['type' => 'INTEGER', 'default' => 1],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('users');

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER'], 'public_id' => ['type' => 'VARCHAR', 'constraint' => 32],
            'share_token' => ['type' => 'VARCHAR', 'constraint' => 64], 'mode' => ['type' => 'VARCHAR', 'constraint' => 16],
            'status' => ['type' => 'VARCHAR', 'constraint' => 12], 'listed' => ['type' => 'INTEGER'],
            'title' => ['type' => 'VARCHAR', 'constraint' => 200], 'description' => ['type' => 'TEXT'],
            'instructions' => ['type' => 'TEXT'], 'revision' => ['type' => 'INTEGER'], 'version' => ['type' => 'INTEGER'],
            'frozen_at' => ['type' => 'DATETIME', 'null' => true], 'time_limit_sec' => ['type' => 'INTEGER', 'null' => true],
            'opens_at' => ['type' => 'DATETIME', 'null' => true], 'closes_at' => ['type' => 'DATETIME', 'null' => true],
            'passcode_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email_mode' => ['type' => 'VARCHAR', 'constraint' => 8], 'phone_mode' => ['type' => 'VARCHAR', 'constraint' => 8],
            'shuffle_questions' => ['type' => 'INTEGER'], 'shuffle_options' => ['type' => 'INTEGER'],
            'feedback' => ['type' => 'VARCHAR', 'constraint' => 12], 'show_score' => ['type' => 'INTEGER'],
            'show_answers' => ['type' => 'INTEGER'], 'show_explain' => ['type' => 'INTEGER'], 'cheat_check' => ['type' => 'INTEGER'],
            'practice_starts' => ['type' => 'INTEGER'], 'published_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'], 'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey('public_id'); $this->forge->addUniqueKey('share_token');
        $this->forge->createTable('quizzes');

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'auto_increment' => true], 'quiz_id' => ['type' => 'INTEGER'],
            'pos' => ['type' => 'INTEGER'], 'type' => ['type' => 'VARCHAR', 'constraint' => 16], 'content' => ['type' => 'TEXT'],
            'media_type' => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true], 'media_src' => ['type' => 'TEXT', 'null' => true],
            'explanation' => ['type' => 'TEXT', 'null' => true], 'points' => ['type' => 'DECIMAL', 'constraint' => '8,2'],
            'time_limit_sec' => ['type' => 'INTEGER', 'null' => true], 'text_answers' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey(['quiz_id', 'pos']); $this->forge->createTable('questions');

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'auto_increment' => true], 'question_id' => ['type' => 'INTEGER'],
            'pos' => ['type' => 'INTEGER'], 'code' => ['type' => 'VARCHAR', 'constraint' => 16], 'content' => ['type' => 'TEXT'],
            'media_type' => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true], 'media_src' => ['type' => 'TEXT', 'null' => true],
            'is_correct' => ['type' => 'INTEGER'], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey(['question_id', 'pos']); $this->forge->createTable('question_options');

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'auto_increment' => true], 'quiz_id' => ['type' => 'INTEGER'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 12],
        ]);
        $this->forge->addKey('id', true); $this->forge->createTable('attempts');
    }

    private function dropTables(): void
    {
        foreach (['attempts', 'question_options', 'questions', 'quizzes', 'users'] as $table) {
            if ($this->authoringDb->tableExists($table)) $this->forge->dropTable($table, true);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) return;
        $items = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) $this->removeDirectory($path); else @unlink($path);
        }
        @rmdir($directory);
    }
}

final class TestUploadedFile extends UploadedFile
{
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_file($this->path);
    }

    public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
    {
        $targetPath = rtrim($targetPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (! is_dir($targetPath)) mkdir($targetPath, 0750, true);
        $name ??= $this->getName();
        $destination = $targetPath . $name;
        if (! copy($this->path, $destination)) return false;
        @unlink($this->path);
        $this->hasMoved = true;
        $this->path = $targetPath;
        $this->name = $name;
        return true;
    }
}
