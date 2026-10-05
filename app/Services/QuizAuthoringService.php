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
    private const MODES    = ['assessment', 'practice'];
    private const TYPES    = ['single_choice', 'multi_select', 'short_text'];
    private const STATUSES = ['draft', 'published', 'closed', 'archived'];

    private readonly BaseConnection $db;
    private readonly Closure $shareCodeFactory;

    public function __construct(
        ?BaseConnection $db = null,
        private readonly ?MediaService $media = null,
        private readonly ?QuizPaperService $papers = null,
        ?Closure $shareCodeFactory = null,
    )
    {
        $this->db = $db ?? Database::connect();
        $this->shareCodeFactory = $shareCodeFactory ?? static fn (): string => QuizShareCode::generate();
    }

    /** @return array<string, mixed> */
    public function create(int $userId, string $title, string $mode): array
    {
        $title = trim($title);
        $mode  = strtolower(trim($mode));
        $fields = [];

        if ($title === '' || mb_strlen($title) > 200) {
            $fields['title'] = 'Enter a title between 1 and 200 characters.';
        }
        if (! in_array($mode, self::MODES, true)) {
            $fields['mode'] = 'Choose assessment or practice.';
        }
        if ($fields !== []) {
            throw new AuthoringException('validation_failed', 'Check the quiz details and try again.', 422, $fields);
        }

        $now = $this->now();
        $row = [
            'user_id'           => $userId,
            'mode'              => $mode,
            'status'            => 'draft',
            'listed'            => 0,
            'title'             => $title,
            'description'       => '',
            'revision'          => 1,
            'version'           => 1,
            'current_paper_id'  => null,
            'time_limit_sec'    => null,
            'opens_at'          => null,
            'closes_at'         => null,
            'passcode_hash'     => null,
            'email_mode'        => $mode === 'practice' ? 'hidden' : 'optional',
            'phone_mode'        => 'hidden',
            'shuffle_questions' => 0,
            'shuffle_options'   => 0,
            'feedback'          => 'at_end',
            'show_score'        => 1,
            'show_answers'      => 0,
            'show_explain'      => 0,
            'cheat_check'       => 0,
            'practice_starts'   => 0,
            'published_at'      => null,
            'created_at'        => $now,
            'updated_at'        => $now,
            'deleted_at'        => null,
        ];

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $shareCode = ($this->shareCodeFactory)();
            if (! QuizShareCode::isShort($shareCode)) {
                throw new AuthoringException('create_failed', 'The quiz could not be created.', 500);
            }
            if ($this->db->table('quizzes')->where('share_token', $shareCode)->countAllResults() > 0) {
                continue;
            }

            $candidate = ['public_id' => bin2hex(random_bytes(16)), 'share_token' => $shareCode] + $row;
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
                continue;
            }

            if (! in_array((int) ($this->db->error()['code'] ?? 0), [19, 1062, 23000], true)) {
                throw new AuthoringException('create_failed', 'The quiz could not be created.', 500);
            }
        }

        throw new AuthoringException('create_failed', 'A unique quiz code could not be generated. Please try again.', 500);
    }

    /** @return array<string, mixed> */
    public function document(int $userId, string $publicId, bool $withDeleted = false): array
    {
        $quiz = $this->ownedQuiz($userId, $publicId, $withDeleted);

        return $this->serializeDocument($quiz);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function save(int $userId, string $publicId, array $payload, bool $overwrite = false): array
    {
        $this->db->transBegin();
        $orphanedMedia = [];

        try {
            $quiz = $this->ownedQuiz($userId, $publicId, false, true);
            if ($quiz['status'] === 'archived') {
                throw new AuthoringException('quiz_archived', 'Restore this quiz from the archive before editing it.', 409);
            }
            $clientVersion = filter_var($payload['version'] ?? null, FILTER_VALIDATE_INT);

            if ($clientVersion === false || $clientVersion === null) {
                throw new AuthoringException('validation_failed', 'A quiz version is required.', 422, [
                    'version' => 'Reload the builder and try again.',
                ]);
            }

            if (! $overwrite && (int) $quiz['version'] !== $clientVersion) {
                throw new AuthoringException(
                    'version_conflict',
                    'This quiz was changed in another tab.',
                    409,
                    ['version' => (string) $quiz['version']],
                );
            }

            $paperFingerprint = $this->paperFingerprint($quiz);
            $normalized = $this->normalizeDocument($payload, $quiz);

            $newVersion = (int) $quiz['version'] + 1;
            $quizUpdate = $normalized['quiz'];
            $quizUpdate['version'] = $newVersion;
            $quizUpdate['revision'] = (int) $quiz['revision'];
            $quizUpdate['updated_at'] = $this->now();

            $this->db->table('quizzes')->where('id', $quiz['id'])->update($quizUpdate);
            $orphanedMedia = $this->syncQuestions((int) $quiz['id'], $normalized['questions']);

            $updated = $this->db->table('quizzes')->where('id', $quiz['id'])->get()->getRowArray();
            if ($updated === null) throw new AuthoringException('save_failed', 'The quiz could not be reloaded.', 500);
            if ($paperFingerprint !== $this->paperFingerprint($updated)) {
                $updated['revision'] = (int) $quiz['revision'] + 1;
                $this->db->table('quizzes')->where('id', $quiz['id'])->update(['revision' => $updated['revision']]);
            }

            if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new AuthoringException('save_failed', 'The draft could not be saved.', 500);
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        $this->deleteMedia($orphanedMedia);

        return $this->document($userId, $publicId);
    }

    /** @return array<string, mixed> */
    public function transition(int $userId, string $publicId, string $action): array
    {
        $allowed = ['publish', 'close', 'reopen', 'archive', 'unarchive', 'trash', 'restore'];
        if (! in_array($action, $allowed, true)) {
            throw new AuthoringException('invalid_action', 'That quiz action is not supported.', 404);
        }

        $withDeleted = $action === 'restore';
        $this->db->transBegin();

        try {
            $quiz = $this->ownedQuiz($userId, $publicId, $withDeleted, true);
            $changes = ['version' => (int) $quiz['version'] + 1, 'updated_at' => $this->now()];

            switch ($action) {
                case 'publish':
                    if (! in_array($quiz['status'], ['draft', 'published', 'closed'], true) || $quiz['deleted_at'] !== null) {
                        $this->invalidTransition('This quiz cannot be published.');
                    }
                    $this->assertPublishable($quiz);
                    $paper = ($this->papers ?? service('quizPapers'))->publish($quiz);
                    $changes['current_paper_id'] = $paper['id'];
                    if ($quiz['status'] === 'draft') {
                        $changes['status'] = 'published';
                    }
                    $changes['published_at'] = $quiz['published_at'] ?? $this->now();
                    break;

                case 'close':
                    if ($quiz['status'] !== 'published' || $quiz['deleted_at'] !== null) {
                        $this->invalidTransition('Only a published quiz can be closed.');
                    }
                    $changes['status'] = 'closed';
                    break;

                case 'reopen':
                    if ($quiz['status'] !== 'closed' || $quiz['deleted_at'] !== null) {
                        $this->invalidTransition('Only a closed quiz can be reopened.');
                    }
                    if ($quiz['current_paper_id'] === null) {
                        $this->invalidTransition('Publish this quiz before reopening it.');
                    }
                    $changes['status'] = 'published';
                    break;

                case 'archive':
                    if ($quiz['status'] === 'archived' || $quiz['deleted_at'] !== null) {
                        $this->invalidTransition('This quiz cannot be archived.');
                    }
                    $changes['status'] = 'archived';
                    break;

                case 'unarchive':
                    if ($quiz['status'] !== 'archived' || $quiz['deleted_at'] !== null) {
                        $this->invalidTransition('Only an archived quiz can be restored.');
                    }
                    $changes['status'] = $quiz['current_paper_id'] === null ? 'draft' : 'closed';
                    break;

                case 'trash':
                    if ($quiz['deleted_at'] !== null) {
                        $this->invalidTransition('This quiz is already in the trash.');
                    }
                    if ($quiz['status'] === 'published') {
                        $this->invalidTransition('Close or archive the quiz before moving it to trash.');
                    }
                    $changes['deleted_at'] = $this->now();
                    break;

                case 'restore':
                    if ($quiz['deleted_at'] === null) {
                        $this->invalidTransition('This quiz is not in the trash.');
                    }
                    $changes['deleted_at'] = null;
                    break;
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

    /** @return array<string, mixed> */
    public function duplicate(int $userId, string $publicId): array
    {
        $source = $this->ownedQuiz($userId, $publicId);
        $sourceDocument = $this->serializeDocument($source);
        $created = $this->create($userId, $this->copyTitle((string) $source['title']), (string) $source['mode']);
        $newQuiz = $this->ownedQuiz($userId, (string) $created['publicId']);
        $copiedPaths = [];

        $this->db->transBegin();
        try {
            $settings = [
                'description', 'time_limit_sec', 'opens_at', 'closes_at',
                'passcode_hash', 'email_mode', 'phone_mode', 'shuffle_questions',
                'shuffle_options', 'feedback', 'show_score', 'show_answers', 'show_explain',
                'cheat_check',
            ];
            $update = ['updated_at' => $this->now()];
            foreach ($settings as $field) {
                $update[$field] = $source[$field];
            }
            if (($source['cover_src'] ?? null) !== null) {
                $update['cover_src'] = $this->copyMedia('image', $source['cover_src'], $userId, (int) $newQuiz['id'], $copiedPaths);
            }
            $this->db->table('quizzes')->where('id', $newQuiz['id'])->update($update);

            foreach ($sourceDocument['questions'] as $question) {
                $questionMedia = $this->copyMedia(
                    $question['media']['type'] ?? null,
                    $this->rawMediaSource('questions', (int) $question['id']),
                    $userId,
                    (int) $newQuiz['id'],
                    $copiedPaths,
                );
                $now = $this->now();
                $this->db->table('questions')->insert([
                    'quiz_id'        => $newQuiz['id'],
                    'pos'            => $question['position'],
                    'type'           => $question['type'],
                    'content'        => $question['content'],
                    'media_type'     => $question['media']['type'] ?? null,
                    'media_src'      => $questionMedia,
                    'explanation'    => $question['explanation'] === '' ? null : $question['explanation'],
                    'text_answers'   => $question['type'] === 'short_text'
                        ? json_encode($question['textAnswers'], JSON_THROW_ON_ERROR)
                        : null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $questionId = (int) $this->db->insertID();

                foreach ($question['options'] as $option) {
                    $optionMedia = $this->copyMedia(
                        $option['media']['type'] ?? null,
                        $this->rawMediaSource('question_options', (int) $option['id']),
                        $userId,
                        (int) $newQuiz['id'],
                        $copiedPaths,
                    );
                    $this->insertOption(
                        $questionId,
                        (int) $option['position'],
                        $option,
                        $now,
                        $optionMedia,
                        $option['media']['type'] ?? null,
                    );
                }
            }

            if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new AuthoringException('duplicate_failed', 'The quiz could not be duplicated.', 500);
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->deleteMedia($copiedPaths);
            $this->db->table('quizzes')->where('id', $newQuiz['id'])->delete();
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
            ->select('q.*, u.display_name, u.timezone')
            ->join('users u', 'u.id = q.user_id')
            ->where('q.share_token', $shareToken)
            ->whereIn('q.status', ['published', 'closed'])
            ->where('q.deleted_at', null)
            ->where('u.active', 1)
            ->where('u.deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }
        try {
            $paper = ($this->papers ?? service('quizPapers'))->currentPublished($row);
            $definition = ($this->papers ?? service('quizPapers'))->definition($paper);
            $student = ($this->papers ?? service('quizPapers'))->studentDocument($paper, str_repeat('0', 32));
        } catch (Throwable) {
            return null;
        }
        $published = $definition['quiz'];
        $questionCount = count($definition['questions']);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $opens = $published['opensAt'] === null ? null : new DateTimeImmutable((string) $published['opensAt'], new DateTimeZone('UTC'));
        $closes = $published['closesAt'] === null ? null : new DateTimeImmutable((string) $published['closesAt'], new DateTimeZone('UTC'));
        $availability = 'available';
        if ($row['status'] === 'closed' || ($closes !== null && $now >= $closes)) {
            $availability = 'closed';
        } elseif ($opens !== null && $now < $opens) {
            $availability = 'scheduled';
        }

        return [
            'title'            => (string) $published['title'],
            'description'      => (string) $published['description'],
            'mode'             => (string) $published['mode'],
            'status'           => (string) $row['status'],
            'teacher'          => (string) $row['display_name'],
            'questionCount'    => $questionCount,
            'timeLimitMinutes' => $this->minutesFromSeconds($published['timeLimitSec']),
            'opensAt'          => $this->utcAtom($published['opensAt']),
            'closesAt'         => $this->utcAtom($published['closesAt']),
            'passcodeRequired' => $paper['passcode_hash'] !== null,
            'availability'     => $availability,
            'shareToken'       => $shareToken,
            'emailMode'        => (string) $published['emailMode'],
            'phoneMode'        => (string) $published['phoneMode'],
            'cheatCheck'       => (bool) $published['cheatCheck'],
            'cover'            => $student['cover'],
        ];
    }

    /** @param array<string, mixed> $payload
     *  @param array<string, mixed> $quiz
     *  @return array{quiz: array<string, mixed>, questions: list<array<string, mixed>>}
     */
    private function normalizeDocument(array $payload, array $quiz): array
    {
        $fields = [];
        $mode = (string) ($payload['mode'] ?? '');
        if (! in_array($mode, self::MODES, true)) {
            $fields['mode'] = 'Choose assessment or practice.';
        }

        $title = $this->text($payload, 'title', 200, $fields);
        $description = $this->text($payload, 'description', 65535, $fields);
        $timeLimit = $this->minutesToSeconds($payload['timeLimitMinutes'] ?? null, 'timeLimitMinutes', $fields);
        $timezone = $this->userTimezone((int) $quiz['user_id']);
        $opensAt  = $this->localDate($payload['opensAtLocal'] ?? null, $timezone, 'opensAtLocal', $fields);
        $closesAt = $this->localDate($payload['closesAtLocal'] ?? null, $timezone, 'closesAtLocal', $fields);
        if ($opensAt !== null && $closesAt !== null && $opensAt >= $closesAt) {
            $fields['closesAtLocal'] = 'Closing time must be after opening time.';
        }

        $emailMode = (string) ($payload['emailMode'] ?? 'optional');
        $phoneMode = (string) ($payload['phoneMode'] ?? 'hidden');
        $feedback  = (string) ($payload['feedback'] ?? 'at_end');
        if (! in_array($emailMode, ['hidden', 'optional', 'required'], true)) {
            $fields['emailMode'] = 'Choose a valid email collection setting.';
        }
        if (! in_array($phoneMode, ['hidden', 'optional', 'required'], true)) {
            $fields['phoneMode'] = 'Choose a valid phone collection setting.';
        }
        if (! in_array($feedback, ['at_end', 'after_each'], true)) {
            $fields['feedback'] = 'Choose a valid feedback setting.';
        }

        $showAnswers = $this->bool($payload['showAnswers'] ?? false);
        $showExplain = $showAnswers && $this->bool($payload['showExplain'] ?? false);
        $passcode    = is_array($payload['passcode'] ?? null) ? $payload['passcode'] : [];
        $passAction  = (string) ($passcode['action'] ?? 'unchanged');
        $passHash    = $quiz['passcode_hash'];
        if (! in_array($passAction, ['unchanged', 'set', 'clear'], true)) {
            $fields['passcode'] = 'Choose a valid passcode action.';
        } elseif ($passAction === 'set') {
            $value = (string) ($passcode['value'] ?? '');
            if (mb_strlen($value) < 4 || strlen($value) > 72) {
                $fields['passcode'] = 'Passcodes must be between 4 characters and 72 bytes.';
            } else {
                $passHash = password_hash($value, PASSWORD_DEFAULT);
            }
        } elseif ($passAction === 'clear') {
            $passHash = null;
        }

        $questions = $this->normalizeQuestions($payload['questions'] ?? null, $fields);

        if ($mode === 'practice') {
            $passHash  = null;
            $emailMode = 'hidden';
            $phoneMode = 'hidden';
        }

        if ($fields !== []) {
            throw new AuthoringException('validation_failed', 'Some quiz fields need attention.', 422, $fields);
        }

        return [
            'quiz' => [
                'mode'              => $mode,
                'listed'            => $this->bool($payload['listed'] ?? false) ? 1 : 0,
                'title'             => $title,
                'description'       => $description,
                'time_limit_sec'    => $timeLimit,
                'opens_at'          => $opensAt,
                'closes_at'         => $closesAt,
                'passcode_hash'     => $passHash,
                'email_mode'        => $emailMode,
                'phone_mode'        => $phoneMode,
                'shuffle_questions' => $this->bool($payload['shuffleQuestions'] ?? false) ? 1 : 0,
                'shuffle_options'   => $this->bool($payload['shuffleOptions'] ?? false) ? 1 : 0,
                'feedback'          => $feedback,
                'show_score'        => $this->bool($payload['showScore'] ?? true) ? 1 : 0,
                'show_answers'      => $showAnswers ? 1 : 0,
                'show_explain'      => $showExplain ? 1 : 0,
                'cheat_check'       => $mode === 'assessment' && $this->bool($payload['cheatCheck'] ?? false) ? 1 : 0,
            ],
            'questions' => $questions,
        ];
    }

    /** @param mixed $input
     *  @param array<string, string> $fields
     *  @return list<array<string, mixed>>
     */
    private function normalizeQuestions(mixed $input, array &$fields): array
    {
        if (! is_array($input)) {
            $fields['questions'] = 'Questions must be an array.';
            return [];
        }
        if (count($input) > 60000) {
            $fields['questions'] = 'This quiz exceeds the database position limit.';
            return [];
        }

        $result = [];
        foreach (array_values($input) as $index => $question) {
            $prefix = 'questions.' . $index;
            if (! is_array($question)) {
                $fields[$prefix] = 'Question data is invalid.';
                continue;
            }

            $type = (string) ($question['type'] ?? '');
            if (! in_array($type, self::TYPES, true)) {
                $fields[$prefix . '.type'] = 'Choose a valid question type.';
            }
            $content = (string) ($question['content'] ?? '');
            if (mb_strlen($content) > 65535) {
                $fields[$prefix . '.content'] = 'Question text is too long.';
            }
            $explanation = (string) ($question['explanation'] ?? '');
            if (mb_strlen($explanation) > 65535) {
                $fields[$prefix . '.explanation'] = 'Explanation text is too long.';
            }
            $id     = $this->nullableId($question['id'] ?? null, $prefix . '.id', $fields);

            $answers = [];
            if (is_array($question['textAnswers'] ?? null)) {
                foreach ($question['textAnswers'] as $answerIndex => $answer) {
                    if (! is_string($answer) || mb_strlen($answer) > 500) {
                        $fields[$prefix . '.textAnswers.' . $answerIndex] = 'Accepted answers must not exceed 500 characters.';
                        continue;
                    }
                    $answers[] = trim($answer);
                }
            } elseif ($type === 'short_text') {
                $fields[$prefix . '.textAnswers'] = 'Accepted answers must be an array.';
            }

            $options = [];
            $optionInput = $question['options'] ?? [];
            if (! is_array($optionInput)) {
                $fields[$prefix . '.options'] = 'Answer choices must be an array.';
                $optionInput = [];
            }
            if (count($optionInput) > 60000) {
                $fields[$prefix . '.options'] = 'This question exceeds the database position limit.';
                $optionInput = [];
            }
            foreach (array_values($optionInput) as $optionIndex => $option) {
                if (! is_array($option)) {
                    $fields[$prefix . '.options.' . $optionIndex] = 'Answer choice data is invalid.';
                    continue;
                }
                $optionContent = (string) ($option['content'] ?? '');
                if (mb_strlen($optionContent) > 65535) {
                    $fields[$prefix . '.options.' . $optionIndex . '.content'] = 'Answer choice text is too long.';
                }
                $options[] = [
                    'id'        => $this->nullableId($option['id'] ?? null, $prefix . '.options.' . $optionIndex . '.id', $fields),
                    'content'   => $optionContent,
                    'isCorrect' => $this->bool($option['isCorrect'] ?? false),
                ];
            }

            $result[] = [
                'id'           => $id,
                'position'     => $index + 1,
                'type'         => $type,
                'content'      => $content,
                'explanation'  => $explanation,
                'textAnswers'  => $type === 'short_text' ? $answers : [],
                'options'      => $type === 'short_text' ? [] : $options,
            ];
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $questions
     *  @return list<string>
     */
    private function syncQuestions(int $quizId, array $questions): array
    {
        $existingQuestions = $this->rowsById('questions', 'quiz_id', $quizId);
        $keptQuestionIds = [];
        $seenQuestionIds = [];
        $orphaned = [];

        // Free the unique (quiz_id, pos) values before applying a new order.
        if ($existingQuestions !== []) {
            $temporaryPosition = 60000;
            foreach (array_keys($existingQuestions) as $existingId) {
                $this->db->table('questions')->where('id', $existingId)->update([
                    'pos' => $temporaryPosition--,
                ]);
            }
        }

        foreach ($questions as $question) {
            $questionId = $question['id'];
            if ($questionId !== null) {
                if (isset($seenQuestionIds[$questionId])) {
                    throw new AuthoringException('duplicate_question', 'A question was submitted more than once.', 422);
                }
                $seenQuestionIds[$questionId] = true;
                if (! isset($existingQuestions[$questionId])) {
                    throw new AuthoringException('foreign_question', 'A question does not belong to this quiz.', 403);
                }
                $keptQuestionIds[] = $questionId;
                $currentQuestion = $existingQuestions[$questionId];
                $this->db->table('questions')->where('id', $questionId)->update([
                    'pos'            => $question['position'],
                    'type'           => $question['type'],
                    'content'        => $question['content'],
                    'explanation'    => $question['explanation'] === '' ? null : $question['explanation'],
                    'text_answers'   => $question['type'] === 'short_text'
                        ? json_encode($question['textAnswers'], JSON_THROW_ON_ERROR)
                        : null,
                    'updated_at'     => $this->now(),
                ]);
                if ($question['type'] === 'short_text') {
                    $orphaned = [...$orphaned, ...$this->deleteAllOptions($questionId)];
                } else {
                    $orphaned = [...$orphaned, ...$this->syncOptions($questionId, $question['options'])];
                }
                continue;
            }

            $now = $this->now();
            $this->db->table('questions')->insert([
                'quiz_id'        => $quizId,
                'pos'            => $question['position'],
                'type'           => $question['type'],
                'content'        => $question['content'],
                'media_type'     => null,
                'media_src'      => null,
                'explanation'    => $question['explanation'] === '' ? null : $question['explanation'],
                'text_answers'   => $question['type'] === 'short_text'
                    ? json_encode($question['textAnswers'], JSON_THROW_ON_ERROR)
                    : null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $questionId = (int) $this->db->insertID();
            $keptQuestionIds[] = $questionId;
            if ($question['type'] !== 'short_text') {
                $this->syncOptions($questionId, $question['options']);
            }
        }

        foreach ($existingQuestions as $id => $row) {
            if (in_array($id, $keptQuestionIds, true)) {
                continue;
            }
            $orphaned = [...$orphaned, ...$this->deleteAllOptions($id)];
            if ($row['media_type'] !== null && $row['media_type'] !== 'video') {
                $orphaned[] = (string) $row['media_src'];
            }
            $this->db->table('questions')->where('id', $id)->delete();
        }

        return $orphaned;
    }

    /** @param list<array<string, mixed>> $options
     *  @return list<string>
     */
    private function syncOptions(int $questionId, array $options): array
    {
        $existing = $this->rowsById('question_options', 'question_id', $questionId);
        $kept = [];
        $seenOptionIds = [];
        $orphaned = [];

        // Free the unique (question_id, pos) values before applying a new order.
        if ($existing !== []) {
            $temporaryPosition = 60000;
            foreach (array_keys($existing) as $existingId) {
                $this->db->table('question_options')->where('id', $existingId)->update([
                    'pos' => $temporaryPosition--,
                ]);
            }
        }
        foreach ($options as $position => $option) {
            $id = $option['id'];
            if ($id !== null) {
                if (isset($seenOptionIds[$id])) {
                    throw new AuthoringException('duplicate_option', 'An answer choice was submitted more than once.', 422);
                }
                $seenOptionIds[$id] = true;
                if (! isset($existing[$id])) {
                    throw new AuthoringException('foreign_option', 'An answer choice does not belong to this question.', 403);
                }
                $kept[] = $id;
                $this->db->table('question_options')->where('id', $id)->update([
                    'pos'        => $position + 1,
                    'content'    => $option['content'],
                    'is_correct' => $option['isCorrect'] ? 1 : 0,
                    'updated_at' => $this->now(),
                ]);
                continue;
            }
            $now = $this->now();
            $kept[] = $this->insertOption($questionId, $position + 1, $option, $now);
        }

        foreach ($existing as $id => $row) {
            if (in_array($id, $kept, true)) {
                continue;
            }
            if ($row['media_type'] !== null && $row['media_type'] !== 'video') {
                $orphaned[] = (string) $row['media_src'];
            }
            $this->db->table('question_options')->where('id', $id)->delete();
        }

        return $orphaned;
    }

    /** @return list<string> */
    private function deleteAllOptions(int $questionId): array
    {
        $rows = $this->db->table('question_options')->where('question_id', $questionId)->get()->getResultArray();
        $paths = [];
        foreach ($rows as $row) {
            if ($row['media_type'] !== null && $row['media_type'] !== 'video') {
                $paths[] = (string) $row['media_src'];
            }
        }
        $this->db->table('question_options')->where('question_id', $questionId)->delete();
        return $paths;
    }

    /** @param array<string, mixed> $option */
    private function insertOption(
        int $questionId,
        int $position,
        array $option,
        string $now,
        ?string $mediaSrc = null,
        ?string $mediaType = null,
    ): int {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            try {
                if ($this->db->table('question_options')->insert([
                    'question_id' => $questionId,
                    'pos'         => $position,
                    'code'        => bin2hex(random_bytes(8)),
                    'content'     => $option['content'],
                    'media_type'  => $mediaType,
                    'media_src'   => $mediaSrc,
                    'is_correct'  => $option['isCorrect'] ? 1 : 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ])) {
                    return (int) $this->db->insertID();
                }
            } catch (Throwable $exception) {
                if (! $this->isDuplicateKey($exception)) {
                    throw $exception;
                }
            }
        }
        throw new AuthoringException('save_failed', 'A unique answer code could not be generated. Please try again.', 500);
    }

    /** @param array<string, mixed> $quiz
     *  @return array<string, mixed>
     */
    private function serializeDocument(array $quiz): array
    {
        $timezone = $this->userTimezone((int) $quiz['user_id']);
        $hasAssessmentHistory = $this->db->table('attempts')
                ->select('id')
                ->where('quiz_id', $quiz['id'])
                ->limit(1)
                ->get()
                ->getRowArray() !== null;
        $publishedMode = null;
        $paper = null;
        if ($quiz['current_paper_id'] !== null) {
            $paper = $this->db->table('quiz_papers')->where('id', $quiz['current_paper_id'])->where('quiz_id', $quiz['id'])->get()->getRowArray();
            if ($paper !== null) {
                $definition = ($this->papers ?? service('quizPapers'))->definition($paper);
                $publishedMode = (string) $definition['quiz']['mode'];
            }
        }
        $resultsAvailable = $publishedMode === 'assessment' || $hasAssessmentHistory;
        $questions = $this->db->table('questions')->where('quiz_id', $quiz['id'])->orderBy('pos')->get()->getResultArray();
        $serialized = [];
        foreach ($questions as $question) {
            $options = $this->db->table('question_options')
                ->where('question_id', $question['id'])
                ->orderBy('pos')
                ->get()->getResultArray();
            $serializedOptions = [];
            foreach ($options as $option) {
                $serializedOptions[] = [
                    'id'        => (string) $option['id'],
                    'code'      => (string) $option['code'],
                    'position'  => (int) $option['pos'],
                    'content'   => (string) $option['content'],
                    'isCorrect' => (bool) $option['is_correct'],
                    'media'     => $this->media?->descriptor(
                        $option['media_type'],
                        $option['media_src'],
                        'option',
                        (int) $option['id'],
                    ),
                ];
            }
            $answers = $question['text_answers'] === null ? [] : json_decode((string) $question['text_answers'], true);
            $serialized[] = [
                'id'           => (string) $question['id'],
                'position'     => (int) $question['pos'],
                'type'         => (string) $question['type'],
                'content'      => (string) $question['content'],
                'explanation'  => (string) ($question['explanation'] ?? ''),
                'textAnswers'  => is_array($answers) ? array_values($answers) : [],
                'media'        => $this->media?->descriptor(
                    $question['media_type'],
                    $question['media_src'],
                    'question',
                    (int) $question['id'],
                ),
                'options'      => $serializedOptions,
            ];
        }

        return [
            'publicId'         => (string) $quiz['public_id'],
            'shareUrl'         => site_url('q/' . $quiz['share_token']),
            'resultsAvailable' => $resultsAvailable,
            'resultsUrl'       => $resultsAvailable ? site_url('results/quizzes/' . $quiz['public_id']) : null,
            'version'          => (int) $quiz['version'],
            'revision'         => (int) $quiz['revision'],
            'publishedRevision'=> $quiz['current_paper_id'] === null ? null : (int) ($paper['revision'] ?? 0),
            'hasPublished'     => $quiz['current_paper_id'] !== null,
            'hasUnpublishedChanges' => $quiz['current_paper_id'] === null || (int) ($paper['revision'] ?? 0) !== (int) $quiz['revision'],
            'publishedMode'    => $publishedMode,
            'mode'             => (string) $quiz['mode'],
            'status'           => (string) $quiz['status'],
            'deleted'          => $quiz['deleted_at'] !== null,
            'listed'           => (bool) $quiz['listed'],
            'title'            => (string) $quiz['title'],
            'description'      => (string) $quiz['description'],
            'cover'            => $this->media?->descriptor(($quiz['cover_src'] ?? null) === null ? null : 'image', $quiz['cover_src'] ?? null, 'cover', (int) $quiz['id']),
            'timeLimitMinutes' => $this->minutesFromSeconds($quiz['time_limit_sec']),
            'timezone'         => $timezone,
            'opensAtLocal'     => $this->localValue($quiz['opens_at'], $timezone),
            'closesAtLocal'    => $this->localValue($quiz['closes_at'], $timezone),
            'passcode'         => ['configured' => $quiz['passcode_hash'] !== null, 'action' => 'unchanged'],
            'emailMode'        => (string) $quiz['email_mode'],
            'phoneMode'        => (string) $quiz['phone_mode'],
            'shuffleQuestions' => (bool) $quiz['shuffle_questions'],
            'shuffleOptions'   => (bool) $quiz['shuffle_options'],
            'feedback'         => (string) $quiz['feedback'],
            'showScore'        => (bool) $quiz['show_score'],
            'showAnswers'      => (bool) $quiz['show_answers'],
            'showExplain'      => (bool) $quiz['show_explain'],
            'cheatCheck'       => (bool) $quiz['cheat_check'],
            'updatedAt'        => $this->utcAtom($quiz['updated_at']),
            'mediaLimits'      => [
                'imageBytes' => 5 * 1024 * 1024,
                'audioBytes' => 20 * 1024 * 1024,
                'serverBytes' => min($this->iniBytes((string) ini_get('upload_max_filesize')), $this->iniBytes((string) ini_get('post_max_size'))),
            ],
            'questions'        => $serialized,
        ];
    }

    private function assertPublishable(array $quiz): void
    {
        $document = $this->serializeDocument($quiz);
        $fields = [];
        if (trim($document['title']) === '') {
            $fields['title'] = 'Add a quiz title before publishing.';
        }
        if ($document['questions'] === []) {
            $fields['questions'] = 'Add at least one question before publishing.';
        }
        foreach ($document['questions'] as $index => $question) {
            $prefix = 'questions.' . $index;
            if (trim($question['content']) === '') {
                $fields[$prefix . '.content'] = 'Add the question text.';
            }
            if ($question['type'] === 'short_text') {
                if (count(array_filter($question['textAnswers'], static fn ($answer): bool => trim((string) $answer) !== '')) < 1) {
                    $fields[$prefix . '.textAnswers'] = 'Add at least one accepted answer.';
                }
                continue;
            }
            if (count($question['options']) < 2) {
                $fields[$prefix . '.options'] = 'Choice questions need at least two answers.';
            }
            foreach ($question['options'] as $optionIndex => $option) {
                if (trim($option['content']) === '' && $option['media'] === null) {
                    $fields[$prefix . '.options.' . $optionIndex] = 'Each answer needs text or media.';
                }
            }
            $correct = count(array_filter($question['options'], static fn (array $option): bool => $option['isCorrect']));
            if ($question['type'] === 'single_choice' && $correct !== 1) {
                $fields[$prefix . '.correct'] = 'Select exactly one correct answer.';
            }
            if ($question['type'] === 'multi_select' && $correct < 1) {
                $fields[$prefix . '.correct'] = 'Select at least one correct answer.';
            }
        }

        if ($fields !== []) {
            throw new AuthoringException('not_publishable', 'Complete the highlighted fields before publishing.', 422, $fields);
        }
    }

    private function isDuplicateKey(Throwable $exception): bool
    {
        if (in_array((int) $exception->getCode(), [19, 1062, 23000], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());
        return str_contains($message, 'duplicate entry') || str_contains($message, 'unique constraint');
    }

    /** @return array<string, mixed> */
    private function ownedQuiz(int $userId, string $publicId, bool $withDeleted = false, bool $lock = false): array
    {
        $sql = 'SELECT * FROM ' . $this->db->prefixTable('quizzes') . ' WHERE public_id = ? AND user_id = ?';
        if (! $withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($lock && $this->db->DBDriver !== 'SQLite3') {
            $sql .= ' FOR UPDATE';
        }
        $row = $this->db->query($sql, [$publicId, $userId])->getRowArray();
        if ($row === null) {
            throw new AuthoringException('quiz_not_found', 'Quiz not found.', 404);
        }
        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    private function rowsById(string $table, string $foreignKey, int $foreignId): array
    {
        $rows = $this->db->table($table)->where($foreignKey, $foreignId)->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row;
        }
        return $result;
    }

    /** @param array<string, mixed> $payload
     *  @param array<string, string> $fields
     */
    private function text(array $payload, string $key, int $max, array &$fields): string
    {
        $value = (string) ($payload[$key] ?? '');
        if (mb_strlen($value) > $max) {
            $fields[$key] = "This field cannot exceed {$max} characters.";
        }
        return $value;
    }


    /** @param array<string, string> $fields */
    private function nullableId(mixed $value, string $key, array &$fields): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            $fields[$key] = 'This identifier is invalid.';
            return null;
        }
        $id = (int) $value;
        if ($id < 1) {
            $fields[$key] = 'This identifier is invalid.';
            return null;
        }
        return $id;
    }

    /** @param array<string, string> $fields */
    private function minutesToSeconds(mixed $value, string $key, array &$fields): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = trim((string) $value);
        if (! preg_match('/^(?:0|[1-9][0-9]{0,3})(?:\.5)?$/D', $text)) {
            $fields[$key] = 'Use half-minute steps from 0.5 to 1440 minutes.';
            return null;
        }
        $halves = (int) round((float) $text * 2);
        if ($halves < 1 || $halves > 2880) {
            $fields[$key] = 'Use half-minute steps from 0.5 to 1440 minutes.';
            return null;
        }
        return $halves * 30;
    }

    private function minutesFromSeconds(mixed $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }
        $halves = intdiv((int) $seconds, 30);
        return intdiv($halves, 2) . ($halves % 2 === 1 ? '.5' : '');
    }

    private function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    /** @param array<string, string> $fields */
    private function localDate(mixed $value, string $timezone, string $key, array &$fields): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            $fields[$key] = 'Enter a valid date and time.';
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone($timezone));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            $fields[$key] = 'Enter a valid date and time.';
            return null;
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function userTimezone(int $userId): string
    {
        $timezone = $this->db->table('users')->select('timezone')->where('id', $userId)->get()->getRow('timezone');
        try {
            new DateTimeZone((string) $timezone);
            return (string) $timezone;
        } catch (Throwable) {
            return 'Asia/Tashkent';
        }
    }

    private function localValue(mixed $value, string $timezone): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($timezone))
            ->format('Y-m-d\TH:i');
    }

    private function utcAtom(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }

    private function copyTitle(string $title): string
    {
        $prefix = 'Copy of ';
        return mb_substr($prefix . $title, 0, 200);
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }
        $number = (float) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    private function paperFingerprint(array $quiz): string
    {
        $definition = [];
        foreach ([
            'title', 'description', 'mode', 'cover_src', 'time_limit_sec',
            'opens_at', 'closes_at', 'email_mode', 'phone_mode', 'shuffle_questions',
            'shuffle_options', 'feedback', 'show_score', 'show_answers', 'show_explain', 'cheat_check',
        ] as $field) {
            $definition[$field] = $quiz[$field] ?? null;
        }
        $definition['passcode_hash'] = ($quiz['passcode_hash'] ?? null) === null
            ? null : bin2hex((string) $quiz['passcode_hash']);
        $definition['questions'] = [];
        foreach ($this->db->table('questions')->where('quiz_id', $quiz['id'])->orderBy('pos')->get()->getResultArray() as $question) {
            $row = [];
            foreach (['id', 'pos', 'type', 'content', 'media_type', 'media_src', 'explanation', 'text_answers'] as $field) {
                $row[$field] = $question[$field] ?? null;
            }
            $row['options'] = [];
            foreach ($this->db->table('question_options')->where('question_id', $question['id'])->orderBy('pos')->get()->getResultArray() as $option) {
                $item = [];
                foreach (['id', 'pos', 'code', 'content', 'media_type', 'media_src', 'is_correct'] as $field) {
                    $item[$field] = $option[$field] ?? null;
                }
                $row['options'][] = $item;
            }
            $definition['questions'][] = $row;
        }
        return hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function rawMediaSource(string $table, int $id): ?string
    {
        $row = $this->db->table($table)->select('media_src')->where('id', $id)->get()->getRowArray();
        return $row === null ? null : $row['media_src'];
    }

    /** @param list<string> $copiedPaths */
    private function copyMedia(?string $type, ?string $src, int $userId, int $quizId, array &$copiedPaths): ?string
    {
        if ($src === null || $type === null || $this->media === null) {
            return $src;
        }
        $copied = $this->media->copyStoredReference($type, $src, $userId, $quizId);
        if ($type !== 'video' && $copied !== null) {
            $copiedPaths[] = $copied;
        }
        return $copied;
    }

    /** @param list<string> $paths */
    private function deleteMedia(array $paths): void
    {
        if ($this->media === null) {
            return;
        }
        foreach (array_unique($paths) as $path) {
            $this->media->deleteStoredReference($path);
        }
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
