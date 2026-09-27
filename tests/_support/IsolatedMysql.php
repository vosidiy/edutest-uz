<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/** Opt-in MySQL tests: a separate temporary server, never the application's connection. */
final class IsolatedMysql
{
    public readonly string $database;
    public readonly string $socket;
    private \mysqli $admin;

    public function __construct(string $socket)
    {
        $root = realpath(dirname($socket));
        if ($root === false || ! preg_match('~^/private/tmp/edutest-responses-mysql-[a-zA-Z0-9]+$~D', $root)) {
            throw new RuntimeException('Tests require a dedicated temporary MySQL server.');
        }
        $this->socket = $root . '/' . basename($socket);
        $this->admin = new \mysqli('localhost', 'root', '', '', 0, $this->socket);
        $data = $this->admin->query('SELECT @@datadir')->fetch_row()[0];
        if (realpath($data) !== $root . '/data') throw new RuntimeException('Refusing a non-test MySQL data directory.');
        $this->database = 'edutest_test_' . bin2hex(random_bytes(8));
        $this->admin->query('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    }

    public function connect(): BaseConnection
    {
        $db = Database::connect(['DBDriver' => 'MySQLi', 'hostname' => $this->socket, 'username' => 'root',
            'password' => '', 'database' => $this->database, 'DBPrefix' => '', 'DBDebug' => true,
            'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_0900_ai_ci'], false);
        $db->initialize();
        $db->query("SET time_zone = '+00:00'");
        return $db;
    }

    public function close(): void
    {
        $this->admin->query('DROP DATABASE `' . $this->database . '`');
        $this->admin->close();
    }
}
