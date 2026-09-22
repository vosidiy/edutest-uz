<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class QuestionOptionModel extends Model
{
    protected $table          = 'question_options';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $allowedFields  = [
        'question_id',
        'pos',
        'code',
        'content',
        'media_type',
        'media_src',
        'is_correct',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'          => 'int',
        'question_id' => 'int',
        'pos'         => 'int',
        'is_correct'  => 'int-bool',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
