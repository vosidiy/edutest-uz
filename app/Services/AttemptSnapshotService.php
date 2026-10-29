<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PlayerException;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/** Builds and reads the immutable quiz copy owned by one assessment attempt. */
final class AttemptSnapshotService
{
    public const SCHEMA_VERSION = 1;

    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null, private readonly ?MediaService $media = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Capture the exact question and option order that will be presented.
     * The caller must hold the quiz row lock for the duration of this call.
     *
     * @return array<string, mixed>
     */
    public function capture(array $quiz): array
    {
        $questions = [];

        foreach ($this->db->table('questions')->where('quiz_id', $quiz['id'])->orderBy('pos')->get()->getResultArray() as $question) {
            $options = [];
            $correctCode = null;

            foreach ($this->db->table('question_options')->where('question_id', $question['id'])->orderBy('pos')->get()->getResultArray() as $option) {
                if ((bool) $option['is_correct']) {
                    if ($correctCode !== null) {
                        throw new PlayerException('invalid_quiz', 409);
                    }
                    $correctCode = (string) $option['code'];
                }

                $options[] = [
                    'id'      => (string) $option['id'],
                    'code'    => (string) $option['code'],
                    'content' => (string) $option['content'],
                    'media'   => $this->storedMedia($option['media_type'], $option['media_src']),
                ];
            }

            if (count($options) < 2 || $correctCode === null) {
                throw new PlayerException('invalid_quiz', 409);
            }

            foreach ($options as $option) {
                if (trim($option['content']) === '' && $option['media'] === null) {
                    throw new PlayerException('invalid_quiz', 409);
                }
            }

            if ((bool) $quiz['shuffle_options']) {
                $this->shuffle($options);
            }

            $questions[] = [
                'id'            => (string) $question['id'],
                'position'      => (int) $question['pos'],
                'content'       => (string) $question['content'],
                'optionsLayout' => (string) $question['options_layout'],
                'media'         => $this->storedMedia($question['media_type'], $question['media_src']),
                'explanation'   => (string) ($question['explanation'] ?? ''),
                'correctCode'   => $correctCode,
                'options'       => $options,
            ];
        }

        if ($questions === []) {
            throw new PlayerException('invalid_quiz', 409);
        }

        $drawCount = $quiz['draw_count'] === null ? count($questions) : (int) $quiz['draw_count'];
        if ($drawCount < 1 || $drawCount > count($questions)) {
            throw new PlayerException('invalid_quiz', 409);
        }

        // Sampling is random even when display shuffling is disabled. In that case the
        // selected subset is restored to authored order before it is stored.
        if ($drawCount < count($questions) || (bool) $quiz['shuffle_questions']) {
            $this->shuffle($questions);
        }
        $questions = array_slice($questions, 0, $drawCount);
        if (! (bool) $quiz['shuffle_questions']) {
            usort($questions, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);
        }
        foreach ($questions as &$question) {
            unset($question['position']);
        }
        unset($question);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'quiz' => [
                'title'        => (string) $quiz['title'],
                'description'  => (string) $quiz['description'],
                'shareToken'   => (string) $quiz['share_token'],
                'timeLimitSec' => (int) $quiz['time_limit_sec'],
                'askEmail'     => (bool) $quiz['ask_email'],
                'askPhone'     => (bool) $quiz['ask_phone'],
                'feedback'     => (string) $quiz['feedback'],
                'showScore'    => (bool) $quiz['show_score'],
                'showAnswers'  => (bool) $quiz['show_answers'],
                'showExplain'  => (bool) $quiz['show_explain'],
            ],
            'cover'     => $this->storedMedia($quiz['cover_src'] === null ? null : 'image', $quiz['cover_src']),
            'questions' => array_values($questions),
        ];
    }

    /** @return array<string, mixed> */
    public function decode(array|string $snapshot): array
    {
        $document = is_array($snapshot) ? $snapshot : json_decode($snapshot, true);
        if (! is_array($document)
            || ($document['schemaVersion'] ?? null) !== self::SCHEMA_VERSION
            || ! is_array($document['quiz'] ?? null)
            || ! is_array($document['questions'] ?? null)
            || ! array_is_list($document['questions'])) {
            throw new RuntimeException('The stored attempt snapshot is invalid.');
        }

        return $document;
    }

    /** Return a browser-safe projection with signed private-media URLs. */
    public function studentDocument(array $attempt): array
    {
        $document = $this->decode($attempt['snapshot']);
        $document['cover'] = $this->attemptMedia($attempt, $document['cover'] ?? null);

        foreach ($document['questions'] as &$question) {
            $question['media'] = $this->attemptMedia($attempt, $question['media'] ?? null);
            foreach ($question['options'] as &$option) {
                $option['media'] = $this->attemptMedia($attempt, $option['media'] ?? null);
            }
            unset($option);

            if (! (bool) ($document['quiz']['showExplain'] ?? false)
                || ! (bool) ($document['quiz']['showAnswers'] ?? false)) {
                $question['explanation'] = '';
            }
        }
        unset($question);

        return $document;
    }

    /** Bounded historical-reference scan used before deleting an old private file. */
    public function referencesPath(string $relative, int $limit = 10000): bool
    {
        $limit = max(1, min(50000, $limit));
        $rows = $this->db->table('attempts')->select('snapshot')->orderBy('id', 'DESC')
            ->limit($limit)->get()->getResultArray();

        foreach ($rows as $row) {
            $snapshot = json_decode((string) $row['snapshot'], true);
            if (is_array($snapshot) && $this->containsSource($snapshot, $relative)) {
                return true;
            }
        }

        return count($rows) === $limit;
    }

    /** @return array{key:string,type:string,src:string}|null */
    private function storedMedia(mixed $type, mixed $src): ?array
    {
        if (! is_string($type) || ! is_string($src) || $src === '') {
            return null;
        }

        return ['key' => bin2hex(random_bytes(16)), 'type' => $type, 'src' => $src];
    }

    /** @return array<string, string>|null */
    private function attemptMedia(array $attempt, mixed $stored): ?array
    {
        if (! is_array($stored)
            || ! is_string($stored['key'] ?? null)
            || ! is_string($stored['type'] ?? null)
            || ! is_string($stored['src'] ?? null)) {
            return null;
        }

        return ($this->media ?? service('media'))->attemptDescriptor(
            $stored['type'],
            $stored['src'],
            (string) $attempt['public_id'],
            $stored['key'],
        );
    }

    /** @param list<mixed> $values */
    private function shuffle(array &$values): void
    {
        for ($index = count($values) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$values[$index], $values[$swap]] = [$values[$swap], $values[$index]];
        }
    }

    private function containsSource(array $value, string $relative): bool
    {
        foreach ($value as $key => $child) {
            if ($key === 'src' && $child === $relative) {
                return true;
            }
            if (is_array($child) && $this->containsSource($child, $relative)) {
                return true;
            }
        }

        return false;
    }
}
