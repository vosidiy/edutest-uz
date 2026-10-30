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
        'status',
        'title',
        'description',
        'cover_src',
        'time_limit_sec',
        'ask_email',
        'ask_phone',
        'shuffle_questions',
        'shuffle_options',
        'draw_count',
        'feedback',
        'show_score',
        'show_answers',
        'show_explain',
        'published_at',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'                => 'int',
        'user_id'           => 'int',
        'time_limit_sec'    => 'int',
        'ask_email'         => 'int-bool',
        'ask_phone'         => 'int-bool',
        'shuffle_questions' => 'int-bool',
        'shuffle_options'   => 'int-bool',
        'draw_count'        => '?int',
        'show_score'        => 'int-bool',
        'show_answers'      => 'int-bool',
        'show_explain'      => 'int-bool',
        'published_at'      => '?datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => '?datetime',
    ];
}
