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
        'shuffle_seed',
        'name',
        'email',
        'phone',
        'ip',
        'agent',
        'status',
        'started_at',
        'last_activity_at',
        'client_activity_at',
        'late_sync',
        'expires_at',
        'deadline_reason',
        'finished_at',
        'ended_reason',
        'score',
        'max_score',
        'percent',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'            => 'int',
        'quiz_id'       => 'int',
        'paper_id'      => 'int',
        'started_at'    => 'datetime',
        'last_activity_at' => 'datetime',
        'client_activity_at' => 'datetime',
        'late_sync' => 'int-bool',
        'expires_at'    => 'datetime',
        'finished_at'   => '?datetime',
        'score'         => '?int',
        'max_score'     => 'int',
    ];
}
