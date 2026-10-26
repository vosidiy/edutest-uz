<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Services\AttemptSnapshotService;
use Tests\Support\PlayerTestCase;

final class StudentMediaTest extends PlayerTestCase
{
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
}
