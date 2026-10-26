<?php

declare(strict_types=1);

namespace Tests\App\Models;

use App\Models\AttemptModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\QuizModel;
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
            AttemptModel::class        => ['attempts', false, false],
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
        $this->assertSame('int-bool', $this->casts(new QuestionOptionModel())['is_correct']);
        $this->assertSame('json-array', $this->casts(new AttemptModel())['snapshot']);
        $this->assertSame('json-array', $this->casts(new AttemptModel())['answers']);
        $this->assertSame('int-bool', $this->casts(new AttemptModel())['late_sync']);

        $this->assertSame('?int', $this->casts(new AttemptModel())['score']);
        $this->assertNotContains('points', $this->property(new QuestionModel(), 'allowedFields'));
        $this->assertNotContains('time_limit_sec', $this->property(new QuestionModel(), 'allowedFields'));
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
