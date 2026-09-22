<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'email',
        'password_hash',
        'display_name',
        'phone',
        'bio',
        'timezone',
        'public_page',
        'last_login_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected array $casts = [
        'id'          => 'int',
        'public_page' => 'int-bool',
        'active'      => 'int-bool',
    ];

    /** @return array<string, mixed>|null */
    public function findActiveByEmail(string $email): ?array
    {
        return $this->where('email', $email)
            ->where('active', 1)
            ->first();
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        return $this->where('id', $id)
            ->where('active', 1)
            ->first();
    }
}
