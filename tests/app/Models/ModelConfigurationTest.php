<?php

declare(strict_types=1);

namespace Tests\App\Models;

use App\Models\AttemptModel;
use App\Models\AttemptAnswerModel;
use App\Models\CheatEventModel;
use App\Models\PracticeKeyModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\QuizModel;
use App\Models\QuizPaperModel;
use App\Models\UserModel;
use CodeIgniter\Model;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;
use ReflectionObject;

final class ModelConfigurationTest extends CIUnitTestCase
{
    public function testUserModelOwnsAccountAndProfilePersistence(): void
    {
        $model = new UserModel();

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('users', $this->property($model, 'table'));
        $this->assertSame('id', $this->property($model, 'primaryKey'));
        $this->assertTrue($this->property($model, 'useTimestamps'));
        $this->assertTrue($this->property($model, 'useSoftDeletes'));
        $this->assertSame(
            [
                'email',
                'password_hash',
                'display_name',
                'phone',
                'bio',
                'timezone',
                'public_page',
                'last_login_at',
            ],
            $this->property($model, 'allowedFields'),
        );
        $this->assertNotContains('password_reset_hash', $this->property($model, 'allowedFields'));
        $this->assertNotContains('password_reset_expires_at', $this->property($model, 'allowedFields'));
        $this->assertSame('int-bool', $this->casts($model)['active']);
        $this->assertSame('int-bool', $this->casts($model)['public_page']);
    }

    public function testApplicationModelsMatchTheirTablesAndTimestampPolicy(): void
    {
        $expectations = [
            QuizModel::class           => ['quizzes', true, true],
            QuestionModel::class       => ['questions', true, false],
            QuestionOptionModel::class => ['question_options', true, false],
            QuizPaperModel::class      => ['quiz_papers', false, false],
            AttemptModel::class        => ['attempts', false, false],
            AttemptAnswerModel::class  => ['attempt_answers', false, false],
            CheatEventModel::class     => ['cheat_events', false, false],
        ];

        foreach ($expectations as $class => [$table, $timestamps, $softDeletes]) {
            $model = new $class();

            $this->assertSame($table, $this->property($model, 'table'), $class);
            $this->assertSame('id', $this->property($model, 'primaryKey'), $class);
            $this->assertSame($timestamps, $this->property($model, 'useTimestamps'), $class);
            $this->assertSame($softDeletes, $this->property($model, 'useSoftDeletes'), $class);
            $this->assertNotContains('id', $this->property($model, 'allowedFields'), $class);
        }
    }

    public function testJsonAndBooleanFieldsAreCastButDecimalsAreNot(): void
    {
        $this->assertSame('int-bool', $this->casts(new QuizModel())['listed']);
        $this->assertSame('?json-array', $this->casts(new QuestionModel())['text_answers']);
        $this->assertSame('int-bool', $this->casts(new QuestionOptionModel())['is_correct']);
        $this->assertSame('json-array', $this->casts(new QuizPaperModel())['definition']);
        $this->assertSame('json-array', $this->casts(new AttemptAnswerModel())['presented_option_codes']);
        $this->assertSame('?json-array', $this->casts(new AttemptAnswerModel())['selected_option_codes']);
        $this->assertSame('json-array', $this->casts(new CheatEventModel())['data']);

        $this->assertSame('?int', $this->casts(new AttemptModel())['score']);
        $this->assertNotContains('points', $this->property(new QuestionModel(), 'allowedFields'));
        $this->assertNotContains('time_limit_sec', $this->property(new QuestionModel(), 'allowedFields'));
    }

    public function testPracticeKeysDeclareTheirCompositeIdentity(): void
    {
        $this->assertSame('practice_keys', PracticeKeyModel::TABLE);
        $this->assertSame(['quiz_id', 'request_key'], PracticeKeyModel::KEY_FIELDS);
        $this->assertFalse(is_subclass_of(PracticeKeyModel::class, \CodeIgniter\Model::class));
    }

    /** @return array<string, string> */
    private function casts(object $model): array
    {
        return $this->property($model, 'casts');
    }

    private function property(object $object, string $name): mixed
    {
        $reflection = new ReflectionObject($object);

        do {
            if ($reflection->hasProperty($name)) {
                return $reflection->getProperty($name)->getValue($object);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof ReflectionClass);

        $this->fail("Property {$name} was not found.");
    }
}
