<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class CheatEventModel extends Model
{
    protected $table         = 'cheat_events';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'attempt_id',
        'event_key',
        'type',
        'happened_at',
        'received_at',
        'duration_ms',
        'data',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id'           => 'int',
        'attempt_id'   => 'int',
        'happened_at'  => '?datetime',
        'received_at'  => 'datetime',
        'duration_ms'  => '?int',
        'data'         => 'json-array',
    ];
}
