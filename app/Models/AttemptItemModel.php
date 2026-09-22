<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class AttemptItemModel extends Model
{
    protected $table         = 'attempt_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'attempt_id',
        'quiz_id',
        'question_id',
        'pos',
        'choice_order',
        'status',
        'started_at',
        'due_at',
        'locked_at',
        'lock_reason',
        'answer_codes',
        'text_answer',
        'save_ver',
        'saved_at',
        'submit_key',
        'submit_hash',
        'result',
        'points',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'            => 'int',
        'attempt_id'    => 'int',
        'quiz_id'       => 'int',
        'question_id'   => 'int',
        'pos'           => 'int',
        'choice_order'  => 'json-array',
        'started_at'    => '?datetime',
        'due_at'        => '?datetime',
        'locked_at'     => '?datetime',
        'answer_codes'  => '?json-array',
        'save_ver'      => 'int',
        'saved_at'      => '?datetime',
    ];
}
