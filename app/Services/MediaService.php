<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthoringException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class MediaService
{
    private const IMAGE_LIMIT = 5 * 1024 * 1024;
    private const AUDIO_LIMIT = 20 * 1024 * 1024;
    private const SIGNED_TTL  = 600;

    private readonly BaseConnection $db;
    private readonly string $root;

    public function __construct(?BaseConnection $db = null, ?string $root = null)
    {
        $this->db   = $db ?? Database::connect();
        $this->root = rtrim($root ?? WRITEPATH . 'uploads/quiz-media', DIRECTORY_SEPARATOR);
    }

    /** @return array<string, mixed> */
    public function attachFile(
        int $userId,
        string $quizPublicId,
        string $target,
        int $targetId,
        UploadedFile $file,
        ?int $expectedVersion = null,
    ): array {
        $this->signingKey();
        $record = $this->ownedTarget($userId, $quizPublicId, $target, $targetId, true);

        if ($record['status'] === 'archived') {
            throw new AuthoringException('quiz_archived', 'Restore this quiz from the archive before changing media.', 409);
        }
        if ($record['frozen_at'] !== null) {
            throw new AuthoringException('quiz_frozen', 'Frozen quiz media cannot be changed.', 409);
        }

        [$mediaType, $extension, $mime] = $this->validateUpload($file);
        $relative = $this->relativePath($userId, (int) $record['quiz_id'], $extension);
        $absolute = $this->absolutePath($relative);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private media directory could not be created.');
        }

        try {
            if ($mediaType === 'image') {
                $this->reencodeImage($file->getTempName(), $absolute, $mime);
            } else {
                $file->move($directory, basename($absolute), true);
            }
        } catch (Throwable $exception) {
            @unlink($absolute);
            throw new AuthoringException('media_store_failed', 'The media file could not be stored.', 500);
        }

        try {
            $result = $this->replaceReference($record, $target, $targetId, $mediaType, $relative, $expectedVersion);
        } catch (Throwable $exception) {
            @unlink($absolute);
            throw $exception;
        }

        if ($record['media_type'] !== null && $record['media_type'] !== 'video') {
            $this->deleteStoredReference((string) $record['media_src']);
        }

        $result['media'] = $this->descriptor($mediaType, $relative, $target, $targetId);

        return $result;
    }

    /** @return array<string, mixed> */
    public function attachVideo(
        int $userId,
        string $quizPublicId,
        string $target,
        int $targetId,
        string $url,
        ?int $expectedVersion = null,
    ): array {
        $this->signingKey();
        $record = $this->ownedTarget($userId, $quizPublicId, $target, $targetId, true);

        if ($record['status'] === 'archived') {
            throw new AuthoringException('quiz_archived', 'Restore this quiz from the archive before changing media.', 409);
        }
        if ($record['frozen_at'] !== null) {
            throw new AuthoringException('quiz_frozen', 'Frozen quiz media cannot be changed.', 409);
        }

        $normalized = $this->normalizeVideoUrl($url);

        if ($normalized === null) {
            throw new AuthoringException(
                'invalid_video_url',
                'Use a valid YouTube, Vimeo, or direct HTTPS MP4 URL.',
                422,
                ['videoUrl' => 'The video URL is not supported.'],
            );
        }

        $result = $this->replaceReference($record, $target, $targetId, 'video', $normalized, $expectedVersion);

        if ($record['media_type'] !== null && $record['media_type'] !== 'video') {
            $this->deleteStoredReference((string) $record['media_src']);
        }

        $result['media'] = $this->descriptor('video', $normalized, $target, $targetId);

        return $result;
    }

    /** @return array<string, mixed> */
    public function remove(
        int $userId,
        string $quizPublicId,
        string $target,
        int $targetId,
        ?int $expectedVersion = null,
    ): array {
        $record = $this->ownedTarget($userId, $quizPublicId, $target, $targetId, true);

        if ($record['status'] === 'archived') {
            throw new AuthoringException('quiz_archived', 'Restore this quiz from the archive before changing media.', 409);
        }
        if ($record['frozen_at'] !== null) {
            throw new AuthoringException('quiz_frozen', 'Frozen quiz media cannot be changed.', 409);
        }

        $result = $this->replaceReference($record, $target, $targetId, null, null, $expectedVersion);

        if ($record['media_type'] !== null && $record['media_type'] !== 'video') {
            $this->deleteStoredReference((string) $record['media_src']);
        }

        $result['media'] = null;

        return $result;
    }

    /** @return array{path: string, mime: string, size: int} */
    public function resolveSigned(string $token): array
    {
        $payload = $this->verifyToken($token);
        $target  = $payload['target'] ?? '';
        $id      = isset($payload['id']) ? (int) $payload['id'] : 0;

        if (! in_array($target, ['question', 'option'], true) || $id < 1) {
            throw new AuthoringException('media_not_found', 'Media not found.', 404);
        }

        $table = $target === 'question' ? 'questions' : 'question_options';
        $row   = $this->db->table($table)
            ->select('media_type, media_src')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if ($row === null || ! in_array($row['media_type'], ['image', 'audio'], true)) {
            throw new AuthoringException('media_not_found', 'Media not found.', 404);
        }

        $path = $this->absolutePath((string) $row['media_src']);

        if (! is_file($path)) {
            throw new AuthoringException('media_not_found', 'Media not found.', 404);
        }

        $mime = (string) (mime_content_type($path) ?: 'application/octet-stream');

        return ['path' => $path, 'mime' => $mime, 'size' => (int) filesize($path)];
    }

    /** @return array<string, string>|null */
    public function descriptor(?string $type, ?string $src, string $target, int $id): ?array
    {
        if ($type === null || $src === null) {
            return null;
        }

        if ($type === 'video') {
            return [
                'type'     => 'video',
                'url'      => $src,
                'embedUrl' => $this->embedUrl($src),
            ];
        }

        $expires = time() + self::SIGNED_TTL;

        return [
            'type'      => $type,
            'url'       => site_url('media/' . $this->signToken($target, $id, $expires)),
            'expiresAt' => gmdate(DATE_ATOM, $expires),
        ];
    }

    public function copyStoredReference(?string $type, ?string $src, int $userId, int $quizId): ?string
    {
        if ($type === null || $src === null || $type === 'video') {
            return $src;
        }

        $source = $this->absolutePath($src);

        if (! is_file($source)) {
            throw new AuthoringException('media_copy_failed', 'A referenced media file is missing.', 409);
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $relative  = $this->relativePath($userId, $quizId, $extension);
        $target    = $this->absolutePath($relative);
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private media directory could not be created.');
        }

        if (! copy($source, $target)) {
            throw new AuthoringException('media_copy_failed', 'A media file could not be duplicated.', 500);
        }

        return $relative;
    }

    public function deleteStoredReference(string $relative): void
    {
        try {
            $path = $this->absolutePath($relative);
        } catch (AuthoringException) {
            return;
        }

        if (is_file($path) && ! @unlink($path)) {
            log_message('warning', 'An orphaned quiz media file could not be removed.');
        }
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function validateUpload(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->hasMoved()) {
            throw new AuthoringException('invalid_upload', 'The upload is missing or invalid.', 422);
        }

        $mime = strtolower($file->getMimeType());
        $size = $file->getSize();
        $map  = [
            'image/jpeg'  => ['image', 'jpg'],
            'image/png'   => ['image', 'png'],
            'image/webp'  => ['image', 'webp'],
            'audio/mpeg'  => ['audio', 'mp3'],
            'audio/mp4'   => ['audio', 'm4a'],
            'audio/x-m4a' => ['audio', 'm4a'],
            'audio/ogg'   => ['audio', 'ogg'],
            'application/ogg' => ['audio', 'ogg'],
            'audio/wav'   => ['audio', 'wav'],
            'audio/x-wav' => ['audio', 'wav'],
        ];

        if (! isset($map[$mime])) {
            throw new AuthoringException('unsupported_media', 'That file type is not supported.', 422);
        }

        [$type, $extension] = $map[$mime];
        $limit = $type === 'image' ? self::IMAGE_LIMIT : self::AUDIO_LIMIT;

        if ($size < 1 || $size > $limit) {
            $label = $type === 'image' ? '5 MiB' : '20 MiB';
            throw new AuthoringException('media_too_large', "The {$type} must not exceed {$label}.", 422);
        }

        if ($type === 'image' && @getimagesize($file->getTempName()) === false) {
            throw new AuthoringException('invalid_image', 'The uploaded image could not be decoded.', 422);
        }

        return [$type, $extension, $mime];
    }

    private function reencodeImage(string $source, string $target, string $mime): void
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png'  => @imagecreatefrompng($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default      => false,
        };

        if ($image === false) {
            throw new RuntimeException('The image decoder rejected the file.');
        }

        try {
            $saved = match ($mime) {
                'image/jpeg' => imagejpeg($image, $target, 88),
                'image/png'  => imagepng($image, $target, 6),
                'image/webp' => function_exists('imagewebp') && imagewebp($image, $target, 88),
                default      => false,
            };
        } finally {
            imagedestroy($image);
        }

        if (! $saved) {
            throw new RuntimeException('The image encoder rejected the file.');
        }
    }

    /** @return array<string, mixed> */
    private function replaceReference(
        array $record,
        string $target,
        int $targetId,
        ?string $type,
        ?string $src,
        ?int $expectedVersion,
    ): array {
        $this->db->transBegin();

        try {
            $sql = 'SELECT version FROM ' . $this->db->prefixTable('quizzes') . ' WHERE id = ?';
            if ($this->db->DBDriver !== 'SQLite3') {
                $sql .= ' FOR UPDATE';
            }
            $version = (int) $this->db->query($sql, [$record['quiz_id']])->getRow('version');
            if ($expectedVersion !== null && $version !== $expectedVersion) {
                throw new AuthoringException(
                    'version_conflict',
                    'This quiz was changed in another tab.',
                    409,
                    ['version' => (string) $version],
                );
            }
            $table = $target === 'question' ? 'questions' : 'question_options';
            $this->db->table($table)->where('id', $targetId)->update([
                'media_type' => $type,
                'media_src'  => $src,
                'updated_at' => $this->now(),
            ]);
            $this->db->table('quizzes')->where('id', $record['quiz_id'])->update([
                'version'    => $version + 1,
                'updated_at' => $this->now(),
            ]);

            if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new RuntimeException('Media update transaction failed.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ['version' => $version + 1];
    }

    /** @return array<string, mixed> */
    private function ownedTarget(
        int $userId,
        string $quizPublicId,
        string $target,
        int $targetId,
        bool $lock,
    ): array {
        if (! in_array($target, ['question', 'option'], true)) {
            throw new AuthoringException('media_not_found', 'Media target not found.', 404);
        }

        if ($target === 'question') {
            $sql = 'SELECT q.id, q.quiz_id, q.media_type, q.media_src, z.version, z.frozen_at, z.status
                    FROM ' . $this->db->prefixTable('questions') . ' q JOIN ' . $this->db->prefixTable('quizzes') . ' z ON z.id = q.quiz_id
                    WHERE q.id = ? AND z.public_id = ? AND z.user_id = ? AND z.deleted_at IS NULL';
        } else {
            $sql = 'SELECT o.id, q.quiz_id, o.media_type, o.media_src, z.version, z.frozen_at, z.status
                    FROM ' . $this->db->prefixTable('question_options') . ' o
                    JOIN ' . $this->db->prefixTable('questions') . ' q ON q.id = o.question_id
                    JOIN ' . $this->db->prefixTable('quizzes') . ' z ON z.id = q.quiz_id
                    WHERE o.id = ? AND z.public_id = ? AND z.user_id = ? AND z.deleted_at IS NULL';
        }

        if ($lock && $this->db->DBDriver !== 'SQLite3') {
            $sql .= ' FOR UPDATE';
        }

        $row = $this->db->query($sql, [$targetId, $quizPublicId, $userId])->getRowArray();

        if ($row === null) {
            throw new AuthoringException('media_not_found', 'Media target not found.', 404);
        }

        return $row;
    }

    private function normalizeVideoUrl(string $url): ?string
    {
        $url   = trim($url);
        $parts = parse_url($url);

        if ($parts === false || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user'])) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($host === 'youtu.be' && preg_match('~^/([A-Za-z0-9_-]{6,20})$~', $path, $match)) {
            return 'https://youtu.be/' . $match[1];
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $id = $query['v'] ?? null;
            if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
                return 'https://www.youtube.com/watch?v=' . $id;
            }
            if (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{6,20})$~', $path, $match)) {
                return 'https://www.youtube.com/watch?v=' . $match[1];
            }
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('~/(?:video/)?([0-9]+)$~', $path, $match)) {
            return 'https://vimeo.com/' . $match[1];
        }

        if (preg_match('/\.mp4$/i', $path)) {
            return $url;
        }

        return null;
    }

    private function embedUrl(string $url): string
    {
        $parts = parse_url($url);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        $path  = (string) ($parts['path'] ?? '');

        if ($host === 'youtu.be') {
            return 'https://www.youtube-nocookie.com/embed/' . ltrim($path, '/');
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            return 'https://www.youtube-nocookie.com/embed/' . ($query['v'] ?? '');
        }

        if (str_contains($host, 'vimeo.com') && preg_match('~/(?:video/)?([0-9]+)$~', $path, $match)) {
            return 'https://player.vimeo.com/video/' . $match[1];
        }

        return $url;
    }

    private function relativePath(int $userId, int $quizId, string $extension): string
    {
        return 'quiz-media/' . $userId . '/' . $quizId . '/' . bin2hex(random_bytes(20)) . '.' . $extension;
    }

    private function absolutePath(string $relative): string
    {
        if (! preg_match('~^quiz-media/[0-9]+/[0-9]+/[a-f0-9]{40}\.[a-z0-9]+$~', $relative)) {
            throw new AuthoringException('invalid_media_path', 'Media not found.', 404);
        }

        $suffix = substr($relative, strlen('quiz-media/'));

        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $suffix);
    }

    private function signToken(string $target, int $id, int $expires): string
    {
        $payload = $this->base64UrlEncode(json_encode([
            'target' => $target,
            'id'     => (string) $id,
            'exp'    => $expires,
        ], JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $this->signingKey(), true);

        return $payload . '.' . $this->base64UrlEncode($signature);
    }

    /** @return array<string, mixed> */
    private function verifyToken(string $token): array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            throw new AuthoringException('media_not_found', 'Media not found.', 404);
        }

        $expected = hash_hmac('sha256', $parts[0], $this->signingKey(), true);
        $provided = $this->base64UrlDecode($parts[1]);

        if ($provided === null || ! hash_equals($expected, $provided)) {
            throw new AuthoringException('media_not_found', 'Media not found.', 404);
        }

        $json = $this->base64UrlDecode($parts[0]);
        $data = $json === null ? null : json_decode($json, true);

        if (! is_array($data) || (int) ($data['exp'] ?? 0) < time()) {
            throw new AuthoringException('media_expired', 'This media link has expired.', 410);
        }

        return $data;
    }

    private function signingKey(): string
    {
        $key = (string) config('Encryption')->key;

        if ($key === '') {
            throw new AuthoringException(
                'media_key_missing',
                'Media signing is unavailable until encryption.key is configured.',
                503,
            );
        }

        return hash('sha256', $key, true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
