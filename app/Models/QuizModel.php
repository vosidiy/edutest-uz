<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class QuizModel extends Model
{
    protected $table            = 'quizzes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';
    protected $allowedFields    = [
        'user_id',
        'public_id',
        'share_token',
        'mode',
        'status',
        'listed',
        'title',
        'description',
        'instructions',
        'revision',
        'version',
        'frozen_at',
        'time_limit_sec',
        'opens_at',
        'closes_at',
        'passcode_hash',
        'email_mode',
        'phone_mode',
        'shuffle_questions',
        'shuffle_options',
        'feedback',
        'show_score',
        'show_answers',
        'show_explain',
        'cheat_check',
        'practice_starts',
        'published_at',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'                => 'int',
        'user_id'           => 'int',
        'listed'            => 'int-bool',
        'revision'          => 'int',
        'version'           => 'int',
        'frozen_at'         => '?datetime',
        'time_limit_sec'    => '?int',
        'opens_at'          => '?datetime',
        'closes_at'         => '?datetime',
        'shuffle_questions' => 'int-bool',
        'shuffle_options'   => 'int-bool',
        'show_score'        => 'int-bool',
        'show_answers'      => 'int-bool',
        'show_explain'      => 'int-bool',
        'cheat_check'       => 'int-bool',
        'practice_starts'   => 'int',
        'published_at'      => '?datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => '?datetime',
    ];
}
