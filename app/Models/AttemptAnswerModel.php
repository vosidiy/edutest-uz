<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class AttemptAnswerModel extends Model
{
    protected $table         = 'attempt_answers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'attempt_id',
        'question_id',
        'pos',
        'presented_option_codes',
        'status',
        'selected_option_codes',
        'text_answer',
        'is_correct',
        'answered_at',
        'client_answered_at',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'                     => 'int',
        'attempt_id'             => 'int',
        'question_id'            => 'int',
        'pos'                    => 'int',
        'presented_option_codes' => 'json-array',
        'selected_option_codes'  => '?json-array',
        'is_correct'             => '?int-bool',
        'answered_at'            => '?datetime',
        'client_answered_at'     => '?datetime',
    ];
}
