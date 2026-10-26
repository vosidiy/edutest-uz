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
        'public_id',
        'token_hash',
        'start_key',
        'name',
        'email',
        'phone',
        'ip',
        'agent',
        'snapshot',
        'answers',
        'answer_version',
        'status',
        'started_at',
        'last_saved_at',
        'expires_at',
        'finished_at',
        'ended_reason',
        'late_sync',
        'score',
        'max_score',
        'percent',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'            => 'int',
        'quiz_id'       => 'int',
        'snapshot'      => 'json-array',
        'answers'       => 'json-array',
        'answer_version'=> 'int',
        'started_at'    => 'datetime',
        'last_saved_at' => 'datetime',
        'expires_at'    => 'datetime',
        'finished_at'   => '?datetime',
        'late_sync'     => 'int-bool',
        'score'         => '?int',
        'max_score'     => 'int',
        'percent'       => '?float',
    ];
}
