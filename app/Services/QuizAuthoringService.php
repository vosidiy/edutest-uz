<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthoringException;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class QuizAuthoringService
{
    private readonly BaseConnection $db;
    private readonly Closure $shareCodeFactory;

    public function __construct(
        ?BaseConnection $db = null,
        private readonly ?MediaService $media = null,
        ?Closure $shareCodeFactory = null,
    ) {
        $this->db = $db ?? Database::connect();
        $this->shareCodeFactory = $shareCodeFactory ?? static fn (): string => QuizShareCode::generate();
    }

    /** @return array<string, string> */
    public function create(int $userId, string $title): array
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new AuthoringException('validation_failed', 'Check the quiz details and try again.', 422, [
                'title' => 'Enter a title between 1 and 200 characters.',
            ]);
        }

        $now = $this->now();
        $defaults = [
            'user_id'           => $userId,
            'status'            => 'draft',
            'listed'            => 0,
            'title'             => $title,
            'description'       => '',
            'cover_src'         => null,
            'version'           => 1,
            'time_limit_sec'    => 7200,
            'ask_email'         => 0,
            'ask_phone'         => 0,
            'feedback'          => 'at_end',
            'shuffle_questions' => 0,
            'shuffle_options'   => 0,
            'draw_count'        => null,
            'show_score'        => 1,
            'show_answers'      => 0,
            'show_explain'      => 0,
            'published_at'      => null,
            'created_at'        => $now,
            'updated_at'        => $now,
            'deleted_at'        => null,
        ];

        for ($try = 0; $try < 20; $try++) {
            $shareCode = ($this->shareCodeFactory)();
            if (! QuizShareCode::isShort($shareCode)) {
                throw new AuthoringException('create_failed', 'The quiz could not be created.', 500);
            }
            $candidate = [
                'public_id'  => bin2hex(random_bytes(16)),
                'share_token'=> $shareCode,
            ] + $defaults;

            try {
                if ($this->db->table('quizzes')->insert($candidate)) {
                    return [
                        'publicId' => $candidate['public_id'],
                        'editUrl'  => site_url('quizzes/' . $candidate['public_id'] . '/edit'),
                    ];
                }
            } catch (Throwable $exception) {
                if (! $this->isDuplicateKey($exception)) {
                    throw new AuthoringException('create_failed', 'The quiz could not be created.', 500);
                }
            }
        }

        throw new AuthoringException('create_failed', 'A unique quiz code could not be generated. Please try again.', 500);
    }

    /** @return array<string, mixed> */
    public function document(int $userId, string $publicId, bool $withDeleted = false): array
    {
        return $this->serializeDocument($this->ownedQuiz($userId, $publicId, $withDeleted));
    }

    /** @return array<string, mixed> */
    public function save(int $userId, string $publicId, array $payload, bool $overwrite = false): array
    {
        $orphanedMedia = [];
        $this->db->transBegin();
        try {
            $quiz = $this->ownedQuiz($userId, $publicId, false, true);
            if (! $overwrite && (! is_int($payload['version'] ?? null) || $payload['version'] !== (int) $quiz['version'])) {
                throw new AuthoringException('version_conflict', 'This quiz was changed in another tab.', 409, [
                    'version' => (string) $quiz['version'],
                ]);
            }

            $document = $this->normalize($payload);

            $now = $this->now();
            $changes = [
                'title'             => $document['title'],
                'description'       => $document['description'],
                'status'            => $document['status'],
                'listed'            => (int) $document['listed'],
                'time_limit_sec'    => $document['timeLimitSec'],
                'ask_email'         => (int) $document['askEmail'],
                'ask_phone'         => (int) $document['askPhone'],
                'feedback'          => $document['feedback'],
                'shuffle_questions' => (int) $document['shuffleQuestions'],
                'shuffle_options'   => (int) $document['shuffleOptions'],
                'draw_count'        => $document['drawCount'],
                'show_score'        => (int) $document['showScore'],
                'show_answers'      => (int) $document['showAnswers'],
                'show_explain'      => (int) $document['showExplain'],
                'version'           => (int) $quiz['version'] + 1,
                'updated_at'        => $now,
            ];
            if ($quiz['status'] === 'draft' && $document['status'] === 'published') {
                $changes['published_at'] = $now;
            }
            $this->db->table('quizzes')->where('id', $quiz['id'])->update($changes);
            $orphanedMedia = $this->syncQuestions((int) $quiz['id'], $document['questions'], $now);
            if ($document['status'] === 'published') {
                $updatedQuiz = $this->db->table('quizzes')->where('id', $quiz['id'])->get()->getRowArray();
                if ($updatedQuiz === null) {
                    throw new AuthoringException('save_failed', 'The quiz could not be saved.', 500);
                }
                $this->assertPublishableDocument($this->serializeDocument($updatedQuiz));
            }

            if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new AuthoringException('save_failed', 'The quiz could not be saved.', 500);
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        foreach (array_unique($orphanedMedia) as $path) {
            ($this->media ?? service('media'))->deleteStoredReference($path);
        }

        return $this->document($userId, $publicId);
    }

    /** @return array<string, mixed> */
    public function transition(int $userId, string $publicId, string $action): array
    {
        if (! in_array($action, ['trash', 'restore'], true)) {
            throw new AuthoringException('invalid_transition', 'That quiz action is not available.', 409);
        }

        $this->db->transBegin();
        try {
            $quiz = $this->ownedQuiz($userId, $publicId, true, true);
            $changes = ['version' => (int) $quiz['version'] + 1, 'updated_at' => $this->now()];

            if ($action === 'trash') {
                if ($quiz['deleted_at'] !== null) {
                    $this->invalidTransition('This quiz is already in the trash.');
                }
                // Keep status intact so restoring does not silently change Active/Draft state.
                $changes['deleted_at'] = $this->now();
            } else {
                if ($quiz['deleted_at'] === null) {
                    $this->invalidTransition('This quiz is not in the trash.');
                }
                $changes['deleted_at'] = null;
            }

            $this->db->table('quizzes')->where('id', $quiz['id'])->update($changes);
            if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new AuthoringException('transition_failed', 'The quiz status could not be changed.', 500);
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ['quiz' => $this->document($userId, $publicId, $action === 'trash')];
    }

    /** @return array<string, string> */
    public function duplicate(int $userId, string $publicId): array
    {
        $source = $this->ownedQuiz($userId, $publicId);
        $sourceDocument = $this->serializeDocument($source);
        $created = $this->create($userId, $this->copyTitle((string) $source['title']));
        $target = $this->ownedQuiz($userId, $created['publicId']);
        $copied = [];

        $this->db->transBegin();
        try {
            $cover = $this->copyMedia('image', $source['cover_src'], $userId, (int) $target['id'], $copied);
            $this->db->table('quizzes')->where('id', $target['id'])->update([
                'description'       => $source['description'],
                'cover_src'         => $cover,
                'time_limit_sec'    => $source['time_limit_sec'],
                'ask_email'         => $source['ask_email'],
                'ask_phone'         => $source['ask_phone'],
                'feedback'          => $source['feedback'],
                'shuffle_questions' => $source['shuffle_questions'],
                'shuffle_options'   => $source['shuffle_options'],
                'draw_count'        => $source['draw_count'],
                'show_score'        => $source['show_score'],
                'show_answers'      => $source['show_answers'],
                'show_explain'      => $source['show_explain'],
                'updated_at'        => $this->now(),
            ]);

            foreach ($sourceDocument['questions'] as $question) {
                $questionSource = $this->rawMediaSource('questions', (int) $question['id']);
                $questionMedia = $this->copyMedia($question['media']['type'] ?? null, $questionSource, $userId, (int) $target['id'], $copied);
                $now = $this->now();
                $this->db->table('questions')->insert([
                    'quiz_id'        => $target['id'],
                    'pos'            => $question['position'],
                    'content'        => $question['content'],
                    'options_layout' => $question['optionsLayout'],
                    'media_type'     => $question['media']['type'] ?? null,
                    'media_src'      => $questionMedia,
                    'explanation'    => $question['explanation'] === '' ? null : $question['explanation'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $questionId = (int) $this->db->insertID();
                foreach ($question['options'] as $option) {
                    $optionSource = $this->rawMediaSource('question_options', (int) $option['id']);
                    $optionMedia = $this->copyMedia($option['media']['type'] ?? null, $optionSource, $userId, (int) $target['id'], $copied);
                    $this->insertOption($questionId, (int) $option['position'], $option, $now, $optionMedia, $option['media']['type'] ?? null);
                }
            }

            if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new AuthoringException('duplicate_failed', 'The quiz could not be duplicated.', 500);
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            foreach ($copied as $path) {
                ($this->media ?? service('media'))->deleteStoredReference($path);
            }
            $questionIds = array_column(
                $this->db->table('questions')->select('id')->where('quiz_id', $target['id'])->get()->getResultArray(),
                'id',
            );
            if ($questionIds !== []) {
                $this->db->table('question_options')->whereIn('question_id', $questionIds)->delete();
                $this->db->table('questions')->where('quiz_id', $target['id'])->delete();
            }
            $this->db->table('quizzes')->where('id', $target['id'])->delete();
            throw $exception;
        }

        return $created;
    }

    /** @return array<string, mixed>|null */
    public function publicSummary(string $shareToken): ?array
    {
        if (! QuizShareCode::isValid($shareToken)) {
            return null;
        }
        $row = $this->db->table('quizzes q')
            ->select('q.*, u.display_name')
            ->join('users u', 'u.id = q.user_id')
            ->where('q.share_token', $shareToken)
            ->where('q.status', 'published')
            ->where('q.deleted_at', null)
            ->where('u.active', 1)
            ->where('u.deleted_at', null)
            ->get()->getRowArray();
        if ($row === null) {
            return null;
        }

        $pool = $this->db->table('questions')->where('quiz_id', $row['id'])->countAllResults();
        if ($pool < 1) {
            return null;
        }

        return [
            'title'            => (string) $row['title'],
            'description'      => (string) $row['description'],
            'status'           => 'published',
            'teacher'          => (string) $row['display_name'],
            'questionCount'    => $row['draw_count'] === null ? $pool : min($pool, (int) $row['draw_count']),
            'timeLimitMinutes' => number_format(((int) $row['time_limit_sec']) / 60, 1, '.', ''),
            'shareToken'       => $shareToken,
            'askEmail'         => (bool) $row['ask_email'],
            'askPhone'         => (bool) $row['ask_phone'],
            'cover'            => ($this->media ?? service('media'))->descriptor(
                $row['cover_src'] === null ? null : 'image',
                $row['cover_src'],
                'cover',
                (int) $row['id'],
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function normalize(array $payload): array
    {
        $fields = [];
        $title = $this->text($payload, 'title', 200, $fields);
        $description = $this->text($payload, 'description', 65535, $fields, true);
        $status = is_string($payload['status'] ?? null) ? $payload['status'] : '';
        if (! in_array($status, ['draft', 'published'], true)) {
            $fields['status'] = 'Choose Draft or Active.';
        }

        $minutes = $payload['timeLimitMinutes'] ?? null;
        if ((is_int($minutes) && $minutes >= 1 && $minutes <= 120)
            || (is_string($minutes) && ctype_digit($minutes) && (int) $minutes >= 1 && (int) $minutes <= 120)) {
            $minutes = (int) $minutes;
        } else {
            $fields['timeLimitMinutes'] = 'Choose a whole-minute timer from 1 to 120 minutes.';
            $minutes = 0;
        }
        $seconds = $minutes * 60;

        $feedback = is_string($payload['feedback'] ?? null) ? $payload['feedback'] : '';
        if (! in_array($feedback, ['at_end', 'after_each'], true)) {
            $fields['feedback'] = 'Choose when feedback is shown.';
        }
        $showAnswers = $this->bool($payload['showAnswers'] ?? false);
        $showExplain = $this->bool($payload['showExplain'] ?? false);
        if ($showExplain && ! $showAnswers) {
            $fields['showExplain'] = 'Correct answers must be shown before explanations can be shown.';
        }

        $questions = $this->normalizeQuestions($payload['questions'] ?? null, $fields);
        $drawCount = $payload['drawCount'] ?? null;
        if ($drawCount === '' || $drawCount === null) {
            $drawCount = null;
        } elseif ((is_int($drawCount) || (is_string($drawCount) && ctype_digit($drawCount))) && (int) $drawCount > 0 && (int) $drawCount <= 65535) {
            $drawCount = (int) $drawCount;
            if ($drawCount > count($questions)) {
                $drawCount = null;
            }
        } else {
            $fields['drawCount'] = 'Use a positive whole number or leave draw count empty.';
        }

        if ($fields !== []) {
            throw new AuthoringException('validation_failed', 'Check the quiz details and try again.', 422, $fields);
        }

        return [
            'title'             => $title,
            'description'       => $description,
            'status'            => $status,
            'listed'            => $this->bool($payload['listed'] ?? false),
            'timeLimitSec'      => $seconds,
            'askEmail'          => $this->bool($payload['askEmail'] ?? false),
            'askPhone'          => $this->bool($payload['askPhone'] ?? false),
            'feedback'          => $feedback,
            'shuffleQuestions'  => $this->bool($payload['shuffleQuestions'] ?? false),
            'shuffleOptions'    => $this->bool($payload['shuffleOptions'] ?? false),
            'drawCount'         => $drawCount,
            'showScore'         => $this->bool($payload['showScore'] ?? false),
            'showAnswers'       => $showAnswers,
            'showExplain'       => $showExplain,
            'questions'         => $questions,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function normalizeQuestions(mixed $input, array &$fields): array
    {
        if (! is_array($input) || ! array_is_list($input) || count($input) > 1000) {
            $fields['questions'] = 'Questions must be a list of at most 1000 items.';
            return [];
        }
        $questions = [];
        foreach ($input as $index => $question) {
            $prefix = 'questions.' . $index;
            if (! is_array($question)) {
                $fields[$prefix] = 'Invalid question.';
                continue;
            }
            $content = is_string($question['content'] ?? null) ? trim($question['content']) : '';
            $explanation = is_string($question['explanation'] ?? null) ? trim($question['explanation']) : '';
            if (mb_strlen($content) > 65535) $fields[$prefix . '.content'] = 'Question text is too long.';
            if (mb_strlen($explanation) > 900) $fields[$prefix . '.explanation'] = 'Explanation is too long.';
            $layout = is_string($question['optionsLayout'] ?? null) ? $question['optionsLayout'] : 'list';
            if (! in_array($layout, ['list', 'grid'], true)) $fields[$prefix . '.optionsLayout'] = 'Choose list or grid.';

            $optionsInput = $question['options'] ?? [];
            $options = [];
            if (! is_array($optionsInput) || ! array_is_list($optionsInput) || count($optionsInput) > 100) {
                $fields[$prefix . '.options'] = 'Answers must be a list of at most 100 items.';
            } else {
                foreach ($optionsInput as $optionIndex => $option) {
                    if (! is_array($option)) {
                        $fields[$prefix . '.options.' . $optionIndex] = 'Invalid answer.';
                        continue;
                    }
                    $optionContent = is_string($option['content'] ?? null) ? trim($option['content']) : '';
                    if ($optionContent === '') {
                        $optionContent = 'Option ' . ($optionIndex + 1);
                    }
                    if (mb_strlen($optionContent) > 65535) $fields[$prefix . '.options.' . $optionIndex] = 'Answer text is too long.';
                    $options[] = [
                        'id'        => $this->nullableId($option['id'] ?? null, $prefix . '.options.' . $optionIndex . '.id', $fields),
                        'content'   => $optionContent,
                        'isCorrect' => $this->bool($option['isCorrect'] ?? false),
                    ];
                }
                while (count($options) < 2) {
                    $optionIndex = count($options);
                    $options[] = [
                        'id'        => null,
                        'content'   => 'Option ' . ($optionIndex + 1),
                        'isCorrect' => false,
                    ];
                }
                $correctFound = false;
                foreach ($options as &$option) {
                    if ($option['isCorrect'] && ! $correctFound) {
                        $correctFound = true;
                    } else {
                        $option['isCorrect'] = false;
                    }
                }
                unset($option);
                if (! $correctFound) {
                    $options[0]['isCorrect'] = true;
                }
            }
            $questions[] = [
                'id'            => $this->nullableId($question['id'] ?? null, $prefix . '.id', $fields),
                'content'       => $content,
                'optionsLayout' => $layout,
                'explanation'   => $explanation,
                'options'       => $options,
            ];
        }

        return $questions;
    }

    /** @return list<string> */
    private function syncQuestions(int $quizId, array $questions, string $now): array
    {
        $existing = array_column($this->db->table('questions')->where('quiz_id', $quizId)->get()->getResultArray(), null, 'id');
        $kept = [];
        $orphaned = [];

        // Park existing rows outside the authored range before applying a reorder.
        // This avoids transient collisions with UNIQUE (quiz_id, pos).
        foreach (array_keys($existing) as $index => $id) {
            $this->db->table('questions')->where('id', $id)->update(['pos' => 60000 + $index]);
        }

        foreach ($questions as $position => $question) {
            $questionId = $question['id'];
            if ($questionId === null) {
                $this->db->table('questions')->insert([
                    'quiz_id'        => $quizId,
                    'pos'            => $position + 1,
                    'content'        => $question['content'],
                    'options_layout' => $question['optionsLayout'],
                    'media_type'     => null,
                    'media_src'      => null,
                    'explanation'    => $question['explanation'] === '' ? null : $question['explanation'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $questionId = (int) $this->db->insertID();
            } else {
                if (! isset($existing[$questionId])) {
                    throw new AuthoringException('invalid_question', 'A question does not belong to this quiz.', 422);
                }
                $this->db->table('questions')->where('id', $questionId)->update([
                    'pos'            => $position + 1,
                    'content'        => $question['content'],
                    'options_layout' => $question['optionsLayout'],
                    'explanation'    => $question['explanation'] === '' ? null : $question['explanation'],
                    'updated_at'     => $now,
                ]);
            }
            $kept[$questionId] = true;
            $orphaned = array_merge($orphaned, $this->syncOptions($questionId, $question['options'], $now));
        }

        foreach ($existing as $id => $row) {
            if (isset($kept[(int) $id])) continue;
            if ($row['media_src'] !== null && $row['media_type'] !== 'video') $orphaned[] = (string) $row['media_src'];
            foreach ($this->db->table('question_options')->where('question_id', $id)->get()->getResultArray() as $option) {
                if ($option['media_src'] !== null && $option['media_type'] !== 'video') $orphaned[] = (string) $option['media_src'];
            }
            $this->db->table('question_options')->where('question_id', $id)->delete();
            $this->db->table('questions')->where('id', $id)->delete();
        }

        return $orphaned;
    }

    /** @return list<string> */
    private function syncOptions(int $questionId, array $options, string $now): array
    {
        $existing = array_column($this->db->table('question_options')->where('question_id', $questionId)->get()->getResultArray(), null, 'id');
        $kept = [];
        $orphaned = [];
        foreach (array_keys($existing) as $index => $id) {
            $this->db->table('question_options')->where('id', $id)->update(['pos' => 65000 + $index]);
        }
        foreach ($options as $position => $option) {
            $optionId = $option['id'];
            if ($optionId === null) {
                $this->insertOption($questionId, $position + 1, $option, $now);
                $optionId = (int) $this->db->insertID();
            } else {
                if (! isset($existing[$optionId])) {
                    throw new AuthoringException('invalid_option', 'An answer does not belong to this question.', 422);
                }
                $this->db->table('question_options')->where('id', $optionId)->update([
                    'pos'        => $position + 1,
                    'content'    => $option['content'],
                    'is_correct' => (int) $option['isCorrect'],
                    'updated_at' => $now,
                ]);
            }
            $kept[$optionId] = true;
        }
        foreach ($existing as $id => $row) {
            if (isset($kept[(int) $id])) continue;
            if ($row['media_src'] !== null && $row['media_type'] !== 'video') $orphaned[] = (string) $row['media_src'];
            $this->db->table('question_options')->where('id', $id)->delete();
        }

        return $orphaned;
    }

    private function insertOption(int $questionId, int $position, array $option, string $now, ?string $mediaSrc = null, ?string $mediaType = null): void
    {
        for ($try = 0; $try < 20; $try++) {
            try {
                if ($this->db->table('question_options')->insert([
                    'question_id' => $questionId,
                    'pos'         => $position,
                    'code'        => bin2hex(random_bytes(8)),
                    'content'     => $option['content'],
                    'media_type'  => $mediaType,
                    'media_src'   => $mediaSrc,
                    'is_correct'  => (int) $option['isCorrect'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ])) return;
            } catch (Throwable $exception) {
                if (! $this->isDuplicateKey($exception)) throw $exception;
            }
        }
        throw new AuthoringException('save_failed', 'A unique answer code could not be generated.', 500);
    }

    /** @return array<string, mixed> */
    private function serializeDocument(array $quiz): array
    {
        $questions = [];
        foreach ($this->db->table('questions')->where('quiz_id', $quiz['id'])->orderBy('pos')->get()->getResultArray() as $question) {
            $options = [];
            foreach ($this->db->table('question_options')->where('question_id', $question['id'])->orderBy('pos')->get()->getResultArray() as $option) {
                $options[] = [
                    'id'        => (string) $option['id'],
                    'code'      => (string) $option['code'],
                    'position'  => (int) $option['pos'],
                    'content'   => (string) $option['content'],
                    'isCorrect' => (bool) $option['is_correct'],
                    'media'     => ($this->media ?? service('media'))->descriptor($option['media_type'], $option['media_src'], 'option', (int) $option['id']),
                ];
            }
            $questions[] = [
                'id'            => (string) $question['id'],
                'position'      => (int) $question['pos'],
                'content'       => (string) $question['content'],
                'optionsLayout' => (string) $question['options_layout'],
                'explanation'   => (string) ($question['explanation'] ?? ''),
                'media'         => ($this->media ?? service('media'))->descriptor($question['media_type'], $question['media_src'], 'question', (int) $question['id']),
                'options'       => $options,
            ];
        }

        $history = $this->db->table('attempts')->where('quiz_id', $quiz['id'])->countAllResults() > 0;
        return [
            'publicId'           => (string) $quiz['public_id'],
            'shareUrl'           => site_url('q/' . $quiz['share_token']),
            'resultsAvailable'   => $history,
            'resultsUrl'         => $history ? site_url('results/quizzes/' . $quiz['public_id']) : null,
            'version'            => (int) $quiz['version'],
            'status'             => (string) $quiz['status'],
            'deleted'            => $quiz['deleted_at'] !== null,
            'listed'             => (bool) $quiz['listed'],
            'title'              => (string) $quiz['title'],
            'description'        => (string) $quiz['description'],
            'cover'              => ($this->media ?? service('media'))->descriptor($quiz['cover_src'] === null ? null : 'image', $quiz['cover_src'], 'cover', (int) $quiz['id']),
            'timeLimitSec'       => (int) $quiz['time_limit_sec'],
            'timeLimitMinutes'   => max(1, (int) ceil(((int) $quiz['time_limit_sec']) / 60)),
            'askEmail'           => (bool) $quiz['ask_email'],
            'askPhone'           => (bool) $quiz['ask_phone'],
            'feedback'           => (string) $quiz['feedback'],
            'shuffleQuestions'   => (bool) $quiz['shuffle_questions'],
            'shuffleOptions'     => (bool) $quiz['shuffle_options'],
            'drawCount'          => $quiz['draw_count'] === null ? null : (int) $quiz['draw_count'],
            'showScore'          => (bool) $quiz['show_score'],
            'showAnswers'        => (bool) $quiz['show_answers'],
            'showExplain'        => (bool) $quiz['show_explain'],
            'publishedAt'        => $this->utcAtom($quiz['published_at']),
            'updatedAt'          => $this->utcAtom($quiz['updated_at']),
            'mediaLimits'        => [
                'imageBytes'  => 5 * 1024 * 1024,
                'audioBytes'  => 20 * 1024 * 1024,
                'serverBytes' => min($this->iniBytes((string) ini_get('upload_max_filesize')), $this->iniBytes((string) ini_get('post_max_size'))),
            ],
            'questions'          => $questions,
        ];
    }

    private function assertPublishableDocument(array $document): void
    {
        $fields = [];
        if (trim((string) $document['title']) === '') $fields['title'] = 'Add a quiz title before making this quiz Active.';
        if ($document['questions'] === []) $fields['questions'] = 'Add at least one question before making this quiz Active.';
        foreach ($document['questions'] as $index => $question) {
            $prefix = 'questions.' . $index;
            if (count($question['options']) < 2) $fields[$prefix . '.options'] = 'Add at least two answers.';
            foreach ($question['options'] as $optionIndex => $option) {
                if (trim((string) $option['content']) === '') {
                    $fields[$prefix . '.options.' . $optionIndex] = 'Each answer needs text.';
                }
            }
            if (count(array_filter($question['options'], static fn (array $option): bool => (bool) $option['isCorrect'])) !== 1) {
                $fields[$prefix . '.correct'] = 'Select exactly one correct answer.';
            }
        }
        if ($fields !== []) {
            throw new AuthoringException('not_publishable', (string) reset($fields), 422, $fields);
        }
    }

    private function ownedQuiz(int $userId, string $publicId, bool $withDeleted = false, bool $lock = false): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $publicId) !== 1) {
            throw new AuthoringException('quiz_not_found', 'Quiz not found.', 404);
        }
        $sql = 'SELECT * FROM ' . $this->db->prefixTable('quizzes') . ' WHERE public_id = ? AND user_id = ?';
        if (! $withDeleted) $sql .= ' AND deleted_at IS NULL';
        if ($lock && $this->db->DBDriver !== 'SQLite3') $sql .= ' FOR UPDATE';
        $quiz = $this->db->query($sql, [$publicId, $userId])->getRowArray();
        if ($quiz === null) throw new AuthoringException('quiz_not_found', 'Quiz not found.', 404);
        return $quiz;
    }

    private function text(array $payload, string $key, int $max, array &$fields, bool $emptyAllowed = false): string
    {
        $value = is_string($payload[$key] ?? null) ? trim($payload[$key]) : '';
        if ((! $emptyAllowed && $value === '') || mb_strlen($value) > $max) {
            $fields[$key] = $emptyAllowed ? 'This text is too long.' : 'This field is required.';
        }
        return $value;
    }

    private function nullableId(mixed $value, string $key, array &$fields): ?int
    {
        if ($value === null || $value === '') return null;
        if ((is_int($value) && $value > 0) || (is_string($value) && ctype_digit($value) && (int) $value > 0)) return (int) $value;
        $fields[$key] = 'Invalid record identifier.';
        return null;
    }

    private function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private function rawMediaSource(string $table, int $id): ?string
    {
        $row = $this->db->table($table)->select('media_src')->where('id', $id)->get()->getRowArray();
        return $row === null || $row['media_src'] === null ? null : (string) $row['media_src'];
    }

    private function copyMedia(?string $type, ?string $src, int $userId, int $quizId, array &$copied): ?string
    {
        $target = ($this->media ?? service('media'))->copyStoredReference($type, $src, $userId, $quizId);
        if ($target !== null && $target !== $src && $type !== 'video') $copied[] = $target;
        return $target;
    }

    private function copyTitle(string $title): string
    {
        $suffix = ' (copy)';
        return mb_substr($title, 0, 200 - mb_strlen($suffix)) . $suffix;
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') return PHP_INT_MAX;
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    private function utcAtom(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    private function isDuplicateKey(Throwable $exception): bool
    {
        return in_array((int) $exception->getCode(), [19, 1062, 23000], true)
            || in_array((int) ($this->db->error()['code'] ?? 0), [19, 1062, 23000], true);
    }

    private function invalidTransition(string $message): never
    {
        throw new AuthoringException('invalid_transition', $message, 409);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
