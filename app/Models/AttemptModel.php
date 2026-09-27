<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class AttemptModel extends Model
{
    protected $table         = 'attempts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'quiz_id',
        'paper_id',
        'public_id',
        'token_hash',
        'start_key',
        'start_hash',
        'name',
        'email',
        'phone',
        'ip',
        'agent',
        'status',
        'phase',
        'current_pos',
        'version',
        'settings',
        'responses',
        'started_at',
        'total_due_at',
        'close_at',
        'due_at',
        'submitted_at',
        'finish_reason',
        'late_sync',
        'score',
        'max_score',
        'percent',
        'updated_at',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'            => 'int',
        'quiz_id'       => 'int',
        'paper_id'      => 'int',
        'current_pos'   => '?int',
        'version'       => 'int',
        'late_sync'     => 'int-bool',
        'settings'      => 'json-array',
        'responses'     => 'json-array',
        'started_at'    => 'datetime',
        'total_due_at'  => '?datetime',
        'close_at'      => '?datetime',
        'due_at'        => '?datetime',
        'submitted_at'  => '?datetime',
        'updated_at'    => 'datetime',
    ];
}
