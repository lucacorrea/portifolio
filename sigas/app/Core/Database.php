<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\DatabaseConfig;
use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = DatabaseConfig::values();

        try {
            self::$connection = new PDO(DatabaseConfig::dsn(), $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            Logger::application('Database connection failed.', ['code' => $exception->getCode()]);

            throw new RuntimeException('Database connection failed.');
        }

        return self::$connection;
    }
}
