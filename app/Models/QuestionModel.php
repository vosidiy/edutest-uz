<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class QuestionModel extends Model
{
    protected $table          = 'questions';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $allowedFields  = [
        'quiz_id',
        'pos',
        'type',
        'content',
        'media_type',
        'media_src',
        'explanation',
        'points',
        'time_limit_sec',
        'text_answers',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'             => 'int',
        'quiz_id'        => 'int',
        'pos'            => 'int',
        'time_limit_sec' => '?int',
        'text_answers'   => '?json-array',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}
