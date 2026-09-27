<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PlayerException;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/** Immutable definitions used by student runs and historical result review. */
final class QuizPaperService
{
    public const SCHEMA_VERSION = 2;

    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null, private readonly ?MediaService $media = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** Called while the owning quiz row is locked. @return array<string, mixed> */
    public function current(array $quiz): array
    {
        $paper = $this->db->table('quiz_papers')
            ->where('quiz_id', $quiz['id'])
            ->where('revision', $quiz['revision'])
            ->get()->getRowArray();
        if ($paper !== null) {
            return $paper;
        }

        $row = [
            'quiz_id'    => $quiz['id'],
            'public_id'  => bin2hex(random_bytes(16)),
            'revision'   => $quiz['revision'],
            'definition' => json_encode($this->capture($quiz), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $this->now(),
        ];
        if (! $this->db->table('quiz_papers')->insert($row)) {
            throw new RuntimeException('The quiz paper could not be created.');
        }
        $row['id'] = (string) $this->db->insertID();

        return $row;
    }

    /** @return array<string, mixed> */
    public function findForAttempt(array $attempt): array
    {
        $paper = $this->db->table('quiz_papers')
            ->where('id', $attempt['paper_id'])->where('quiz_id', $attempt['quiz_id'])
            ->get()->getRowArray();
        if ($paper === null) {
            throw new PlayerException('not_found', 404);
        }
        return $paper;
    }

    /** @return array<string, mixed> */
    public function findForPractice(string $publicId, int|string $quizId): array
    {
        $paper = $this->db->table('quiz_papers')
            ->where('public_id', $publicId)->where('quiz_id', $quizId)
            ->get()->getRowArray();
        if ($paper === null) {
            throw new PlayerException('not_found', 404);
        }
        return $paper;
    }

    /** @return array<string, mixed> */
    public function definition(array $paper): array
    {
        $definition = is_array($paper['definition'] ?? null)
            ? $paper['definition']
            : json_decode((string) ($paper['definition'] ?? ''), true);
        if (! is_array($definition) || ($definition['schemaVersion'] ?? null) !== self::SCHEMA_VERSION
            || ! is_array($definition['quiz'] ?? null) || ! is_array($definition['questions'] ?? null)) {
            throw new RuntimeException('The stored quiz paper is invalid.');
        }
        return $definition;
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function studentDocument(array $paper, array $settings, string $seed): array
    {
        $definition = $this->definition($paper);
        $quiz = $definition['quiz'];
        $questions = [];
        foreach ($definition['questions'] as $storedQuestion) {
            $options = [];
            foreach ($storedQuestion['options'] as $storedOption) {
                $options[] = [
                    'id' => (string) $storedOption['id'], 'code' => (string) $storedOption['code'],
                    'content' => (string) $storedOption['content'],
                    'media' => $this->paperMedia($paper, $storedOption['media'] ?? null),
                ];
            }
            if ($settings['shuffleOptions']) {
                $this->shuffle($options, $seed . ':' . $storedQuestion['id']);
            }
            $questions[] = [
                'id' => (string) $storedQuestion['id'], 'type' => (string) $storedQuestion['type'],
                'content' => (string) $storedQuestion['content'],
                'media' => $this->paperMedia($paper, $storedQuestion['media'] ?? null),
                'explanation' => $settings['showExplain'] && $settings['showAnswers'] ? (string) ($storedQuestion['explanation'] ?? '') : '',
                'correctCodes' => array_values(array_map('strval', $storedQuestion['correctCodes'] ?? [])),
                'acceptedAnswers' => array_values($storedQuestion['acceptedAnswers'] ?? []),
                'options' => $options,
            ];
        }
        if ($settings['shuffleQuestions']) {
            $this->shuffle($questions, $seed);
        }
        return [
            'title' => (string) $quiz['title'], 'description' => (string) $quiz['description'],
            'instructions' => (string) $quiz['instructions'], 'shareToken' => (string) $quiz['shareToken'],
            'settings' => $settings, 'cover' => $this->paperMedia($paper, $quiz['cover'] ?? null),
            'questions' => $questions,
        ];
    }

    public function purgeUnusedPapers(int $limit = 100): void
    {
        $rows = $this->db->table('quiz_papers p')
            ->select('p.id, p.definition')
            ->join('attempts a', 'a.paper_id = p.id', 'left')
            ->join('practice_keys k', 'k.paper_id = p.id', 'left')
            ->where('a.id', null)->where('k.request_key', null)
            ->limit(max(1, min(100, $limit)))->get()->getResultArray();
        foreach ($rows as $row) {
            $definition = json_decode((string) $row['definition'], true);
            $paths = is_array($definition) ? $this->mediaPaths($definition) : [];
            if ($this->db->table('quiz_papers')->where('id', $row['id'])->delete()) {
                foreach ($paths as $path) ($this->media ?? service('media'))->deleteStoredReference($path);
            }
        }
    }

    public function referencesPath(string $relative): bool
    {
        foreach ($this->db->table('quiz_papers')->select('definition')->get()->getResultArray() as $row) {
            $definition = json_decode((string) $row['definition'], true);
            if (is_array($definition) && $this->containsPath($definition, $relative)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, mixed> */
    private function capture(array $quiz): array
    {
        $questions = [];
        foreach ($this->db->table('questions')->where('quiz_id', $quiz['id'])->orderBy('pos')->get()->getResultArray() as $question) {
            $options = [];
            $correctCodes = [];
            foreach ($this->db->table('question_options')->where('question_id', $question['id'])->orderBy('pos')->get()->getResultArray() as $option) {
                if ((bool) $option['is_correct']) {
                    $correctCodes[] = (string) $option['code'];
                }
                $options[] = [
                    'id' => (string) $option['id'], 'position' => (int) $option['pos'],
                    'code' => (string) $option['code'], 'content' => (string) $option['content'],
                    'isCorrect' => (bool) $option['is_correct'],
                    'media' => $this->storedMedia($option['media_type'], $option['media_src']),
                ];
            }
            $accepted = $question['text_answers'] === null ? [] : json_decode((string) $question['text_answers'], true);
            $questions[] = [
                'id' => (string) $question['id'], 'position' => (int) $question['pos'],
                'type' => (string) $question['type'], 'content' => (string) $question['content'],
                'explanation' => (string) ($question['explanation'] ?? ''),
                'acceptedAnswers' => is_array($accepted) ? array_values($accepted) : [],
                'correctCodes' => $correctCodes,
                'media' => $this->storedMedia($question['media_type'], $question['media_src']),
                'options' => $options,
            ];
        }
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'quiz' => [
                'title' => (string) $quiz['title'], 'description' => (string) $quiz['description'],
                'instructions' => (string) $quiz['instructions'], 'shareToken' => (string) $quiz['share_token'],
                'mode' => (string) $quiz['mode'],
                'cover' => $this->storedMedia(($quiz['cover_src'] ?? null) === null ? null : 'image', $quiz['cover_src'] ?? null),
                'timeLimitSec' => $quiz['time_limit_sec'] === null ? null : (int) $quiz['time_limit_sec'],
                'opensAt' => $quiz['opens_at'], 'closesAt' => $quiz['closes_at'],
                'passcodeRequired' => $quiz['passcode_hash'] !== null,
                'emailMode' => (string) $quiz['email_mode'], 'phoneMode' => (string) $quiz['phone_mode'],
                'shuffleQuestions' => (bool) $quiz['shuffle_questions'], 'shuffleOptions' => (bool) $quiz['shuffle_options'],
                'feedback' => (string) $quiz['feedback'], 'showScore' => (bool) $quiz['show_score'],
                'showAnswers' => (bool) $quiz['show_answers'], 'showExplain' => (bool) $quiz['show_explain'],
                'cheatCheck' => (bool) $quiz['cheat_check'],
                'timingPolicy' => 'client_enforced_offline_allowed',
            ],
            'questions' => $questions,
        ];
    }

    /** @return array<string, string>|null */
    private function storedMedia(?string $type, ?string $src): ?array
    {
        return $type === null || $src === null
            ? null
            : ['key' => bin2hex(random_bytes(16)), 'type' => $type, 'src' => $src];
    }

    /** @return array<string, string>|null */
    private function paperMedia(array $paper, mixed $media): ?array
    {
        if (! is_array($media)) {
            return null;
        }
        return ($this->media ?? service('media'))->paperDescriptor(
            (string) $media['type'], (string) $media['src'], (string) $paper['public_id'], (string) $media['key'],
        );
    }

    /** @return list<string> */
    private function mediaPaths(array $value): array
    {
        $paths = [];
        foreach ($value as $key => $child) {
            if ($key === 'src' && is_string($child) && ! str_starts_with($child, 'https://')) $paths[] = $child;
            if (is_array($child)) $paths = [...$paths, ...$this->mediaPaths($child)];
        }
        return array_values(array_unique($paths));
    }

    private function containsPath(array $value, string $relative): bool
    {
        foreach ($value as $key => $child) {
            if ($key === 'src' && $child === $relative) {
                return true;
            }
            if (is_array($child) && $this->containsPath($child, $relative)) {
                return true;
            }
        }
        return false;
    }

    private function shuffle(array &$items, string $seed): void
    {
        usort($items, static fn (array $a, array $b): int => strcmp(hash_hmac('sha256', $a['id'], $seed), hash_hmac('sha256', $b['id'], $seed)));
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
