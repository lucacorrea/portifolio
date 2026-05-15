<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $default = Config::get('database.default', 'mysql');
        $config = Config::get("database.connections.{$default}");

        if (!is_array($config)) {
            throw new RuntimeException('Database connection is not configured.');
        }

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO(
            $dsn,
            (string) $config['username'],
            (string) $config['password'],
            $config['options'] ?? []
        );

        return self::$connection;
    }
}

