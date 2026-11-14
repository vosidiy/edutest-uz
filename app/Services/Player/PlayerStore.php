<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Centralizes transaction retries, row locks, and UTC time conversion for player writes. */
final class PlayerStore
{
    public readonly BaseConnection $db;

    // Wire the database and collaborating services used by this class.
    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    // Run a player mutation with contention-safe transaction retries.
    public function transaction(callable $operation): mixed
    {
        for ($try = 0; ; $try++) {
            if (! $this->db->transBegin()) throw new PlayerException('server_error', 500);
            try {
                $result = $operation();
                if ($this->db->transStatus() === false || ! $this->db->transCommit()) throw new PlayerException('server_error', 500);
                return $result;
            } catch (Throwable $error) {
                // Capture the driver error before rollback clears it. Only retry rolled-back MySQL contention.
                $code = (int) ($this->db->error()['code'] ?: $error->getCode());
                $this->db->transRollback();
                $this->db->resetTransStatus();
                if ($this->db->DBDriver !== 'MySQLi' || ! in_array($code, [1205, 1213], true) || $try >= 2) throw $error;
                usleep(random_int(10000, 30000) * ($try + 1));
            }
        }
    }

    // Lock a specific stored quiz or attempt row for mutation.
    public function lock(string $table, string $id, string $columns = '*'): array
    {
        if (! in_array($table, ['quizzes', 'attempts'], true)) throw new \LogicException('Invalid lock target.');
        if (! in_array($columns, ['*', 'id'], true)) throw new \LogicException('Invalid lock columns.');
        $sql = 'SELECT ' . $columns . ' FROM ' . $this->db->prefixTable($table) . ' WHERE id = ?';
        if ($this->db->DBDriver !== 'SQLite3') $sql .= ' FOR UPDATE';
        $row = $this->db->query($sql, [$id])->getRowArray();
        if ($row === null) throw new PlayerException('not_found', 404);
        return $row;
    }

    // Return the current database timestamp.
    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    // Normalize a timestamp for API output.
    public static function iso(?string $value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }

    // Format a timestamp for database storage.
    public static function date(string $value): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/D', $value)) throw new PlayerException('invalid_progress');
        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) throw new PlayerException('invalid_progress');
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        }
        catch (\Exception) { throw new PlayerException('invalid_progress'); }
    }

    // Calculate a deadline from a start time and duration.
    public static function due(string $start, ?int $seconds): ?string
    {
        return $seconds === null ? null : (new DateTimeImmutable($start, new DateTimeZone('UTC')))->modify('+' . $seconds . ' seconds')->format('Y-m-d H:i:s.u');
    }
}
