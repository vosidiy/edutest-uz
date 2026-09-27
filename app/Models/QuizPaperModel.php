<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class QuizPaperModel extends Model
{
    protected $table = 'quiz_papers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['quiz_id', 'public_id', 'revision', 'definition', 'created_at'];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int', 'quiz_id' => 'int', 'revision' => 'int',
        'definition' => 'json-array', 'created_at' => 'datetime',
    ];
}
