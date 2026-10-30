<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Services\AttemptSnapshotService;
use Tests\Support\PlayerTestCase;

final class StudentMediaTest extends PlayerTestCase
{
    public function testMediaMutationUsesLastWriteWinsAndRefreshesQuizTimestamp(): void
    {
        $quiz = $this->quiz(count: 1);
        $row = $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->get()->getRowArray();
        $this->db->table('quizzes')->where('id', $row['id'])->update(['updated_at' => '2000-01-01 00:00:00.000000']);

        $result = $this->media->remove($quiz['owner'], $quiz['publicId'], 'cover', 0);
        $stored = $this->db->table('quizzes')->where('id', $row['id'])->get()->getRowArray();

        self::assertArrayHasKey('updatedAt', $result);
        self::assertArrayNotHasKey('version', $result);
        self::assertNull($result['media']);
        self::assertNotSame('2000-01-01 00:00:00.000000', $stored['updated_at']);
    }

    public function testAttemptMediaTokenResolvesOnlyFromStoredSnapshot(): void
    {
        $quiz = $this->quiz(count: 1);
        $row = $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->get()->getRowArray();
        $relative = 'quiz-media/' . $quiz['owner'] . '/' . $row['id'] . '/' . str_repeat('a', 40) . '.png';
        $absolute = $this->mediaRoot . '/' . $quiz['owner'] . '/' . $row['id'] . '/' . str_repeat('a', 40) . '.png';
        if (! is_dir(dirname($absolute))) mkdir(dirname($absolute), 0750, true);
        file_put_contents($absolute, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $this->db->table('quizzes')->where('id', $row['id'])->update(['cover_src' => $relative]);

        $attempt = $this->startQuiz($quiz);
        $descriptor = $attempt['quiz']['cover'];
        self::assertNotNull($descriptor);
        self::assertStringNotContainsString($relative, $descriptor['url']);
        self::assertSame($absolute, $this->media->resolveSigned(basename($descriptor['url']))['path']);

        $this->db->table('quizzes')->where('id', $row['id'])->update(['cover_src' => null]);
        self::assertTrue((new AttemptSnapshotService($this->db, $this->media))->referencesPath($relative));
    }

    public function testQuestionAudioRemainsAvailableThroughAttemptProjection(): void
    {
        $quiz = $this->quiz(count: 1);
        $quizRow = $this->db->table('quizzes')->where('public_id', $quiz['publicId'])->get()->getRowArray();
        $question = $this->db->table('questions')->where('quiz_id', $quizRow['id'])->get()->getRowArray();
        $relative = 'quiz-media/' . $quiz['owner'] . '/' . $quizRow['id'] . '/' . str_repeat('b', 40) . '.mp3';
        $absolute = $this->mediaRoot . '/' . $quiz['owner'] . '/' . $quizRow['id'] . '/' . str_repeat('b', 40) . '.mp3';
        if (! is_dir(dirname($absolute))) mkdir(dirname($absolute), 0750, true);
        file_put_contents($absolute, 'test audio');
        $this->db->table('questions')->where('id', $question['id'])->update([
            'media_type' => 'audio',
            'media_src' => $relative,
        ]);

        $attempt = $this->startQuiz($quiz);
        $descriptor = $attempt['quiz']['questions'][0]['media'];
        self::assertSame('audio', $descriptor['type']);
        self::assertSame($absolute, $this->media->resolveSigned(basename($descriptor['url']))['path']);
    }

    public function testLegacyVideoAndInvalidOptionAudioRemainStoredButAreNotProjected(): void
    {
        $attempt = $this->startQuiz($this->quiz(count: 1));
        $row = $this->db->table('attempts')->where('public_id', $attempt['attemptId'])->get()->getRowArray();
        $snapshot = json_decode((string) $row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
        $snapshot['questions'][0]['media'] = [
            'key' => str_repeat('c', 32),
            'type' => 'video',
            'src' => 'https://example.test/legacy.mp4',
        ];
        $snapshot['questions'][0]['options'][0]['media'] = [
            'key' => str_repeat('d', 32),
            'type' => 'audio',
            'src' => 'quiz-media/1/1/' . str_repeat('d', 40) . '.mp3',
        ];
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $this->db->table('attempts')->where('id', $row['id'])->update(['snapshot' => $encoded]);
        $row['snapshot'] = $encoded;

        $projected = (new AttemptSnapshotService($this->db, $this->media))->studentDocument($row);
        self::assertNull($projected['questions'][0]['media']);
        self::assertNull($projected['questions'][0]['options'][0]['media']);
        $stored = $this->db->table('attempts')->select('snapshot')->where('id', $row['id'])->get()->getRowArray();
        self::assertSame('video', json_decode((string) $stored['snapshot'], true)['questions'][0]['media']['type']);
        self::assertNull($this->media->descriptor('video', 'https://example.test/legacy.mp4', 'question', 1));
        self::assertNull($this->media->attemptDescriptor('video', 'https://example.test/legacy.mp4', $attempt['attemptId'], str_repeat('c', 32)));
    }
}
