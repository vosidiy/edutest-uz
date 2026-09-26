<?php

declare(strict_types=1);

namespace Tests\App\Services;

use App\Exceptions\AuthoringException;
use App\Exceptions\PlayerException;
use App\Services\Player\Credentials;
use CodeIgniter\HTTP\Files\UploadedFile;
use Tests\Support\PlayerTestCase;

final class StudentMediaTest extends PlayerTestCase
{
    public function testCoverFormatsReplacementDuplicationAndFreeze(): void
    {
        $quiz = $this->quiz(count: 1);
        $version = $quiz['document']['version'];
        $previous = null;
        foreach (['png', 'jpeg', 'webp'] as $format) {
            $path = tempnam(sys_get_temp_dir(), 'player-cover-');
            $image = imagecreatetruecolor(2, 2);
            $encode = 'image' . $format;
            if (! function_exists($encode)) continue;
            $encode($image, $path); imagedestroy($image);
            try {
                $result = $this->media->attachFile($quiz['owner'], $quiz['publicId'], 'cover', 0, new PlayerUpload($path, 'cover.' . $format, null, filesize($path), UPLOAD_ERR_OK), $version);
            } finally { unlink($path); }
            $version = $result['version'];
            $resolved = $this->media->resolveSigned(basename($result['media']['url']));
            $this->assertSame('image/' . $format, $resolved['mime']);
            if ($previous !== null) $this->assertFileDoesNotExist($previous);
            $previous = $resolved['path'];
        }
        $summary = $this->authoring->publicSummary($quiz['share']);
        $this->assertNotNull($summary['cover']);
        $copy = $this->authoring->duplicate($quiz['owner'], $quiz['publicId']);
        $copyDoc = $this->authoring->document($quiz['owner'], $copy['publicId']);
        $copyFile = $this->media->resolveSigned(basename($copyDoc['cover']['url']))['path'];
        $this->assertNotSame($previous, $copyFile);
        $this->assertSame(file_get_contents($previous), file_get_contents($copyFile));
        $attempt = $this->startQuiz($quiz);
        $this->assertNotNull($attempt['quiz']['cover']);
        try { $this->media->remove($quiz['owner'], $quiz['publicId'], 'cover', 0); $this->fail(); }
        catch (AuthoringException $error) { $this->assertSame('quiz_frozen', $error->errorCode); }
        $this->media->remove($quiz['owner'], $copy['publicId'], 'cover', 0, $copyDoc['version']);
        $this->assertFileDoesNotExist($copyFile);
        $this->assertFileExists($previous);
    }

    public function testFirstStartDuringUploadFailsClosedAndCleansNewFile(): void
    {
        $quiz = $this->quiz();
        $path = tempnam(sys_get_temp_dir(), 'player-race-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $upload = new PlayerUpload($path, 'cover.png', null, filesize($path), UPLOAD_ERR_OK);
        $upload->onMime = function () use ($quiz): void { $this->startQuiz($quiz); };
        try {
            $this->media->attachFile($quiz['owner'], $quiz['publicId'], 'cover', 0, $upload, $quiz['document']['version']);
            $this->fail('A first start during decode must block replacement.');
        } catch (AuthoringException $error) { $this->assertSame('quiz_frozen', $error->errorCode); }
        finally { unlink($path); }
        $this->assertNull($this->authoring->document($quiz['owner'], $quiz['publicId'])['cover']);
        $files = is_dir($this->mediaRoot) ? iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->mediaRoot, \FilesystemIterator::SKIP_DOTS))) : [];
        $this->assertCount(0, array_filter($files, static fn ($file): bool => $file->isFile()));
    }

    public function testInvalidCoverAndNonOwnerAreRejected(): void
    {
        $quiz = $this->quiz();
        $path = tempnam(sys_get_temp_dir(), 'player-invalid-');
        file_put_contents($path, '<svg onload="alert(1)"></svg>');
        try {
            $upload = new PlayerUpload($path, 'photo.png', 'image/png', filesize($path), UPLOAD_ERR_OK);
            try { $this->media->attachFile($quiz['owner'], $quiz['publicId'], 'cover', 0, $upload); $this->fail(); }
            catch (AuthoringException $error) { $this->assertSame('unsupported_media', $error->errorCode); }
            $this->expectException(AuthoringException::class);
            $this->media->attachFile($quiz['owner'] + 1, $quiz['publicId'], 'cover', 0, $upload);
        } finally { unlink($path); }
    }

    public function testSignedCredentialsRejectTamperingExpiryAndMissingKey(): void
    {
        $credentials = new Credentials();
        $token = $credentials->sign('practice', ['quizId' => '9007199254740993'], 60);
        $this->assertSame('9007199254740993', $credentials->verify($token, 'practice')['quizId']);
        foreach ([$token . 'x', $credentials->sign('practice', [], -1)] as $invalid) {
            try { $credentials->verify($invalid, 'practice'); $this->fail(); }
            catch (PlayerException $error) { $this->assertContains($error->status, [401, 410]); }
        }
        config('Encryption')->key = '';
        $this->expectException(PlayerException::class);
        $credentials->sign('admission', [], 60);
    }
}

final class PlayerUpload extends UploadedFile
{
    public ?\Closure $onMime = null;
    public function isValid(): bool { return true; }
    public function getMimeType(): string
    {
        if ($this->onMime !== null) { $callback = $this->onMime; $this->onMime = null; $callback(); }
        return parent::getMimeType();
    }
}
