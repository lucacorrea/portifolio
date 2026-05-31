<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = Config::database();

        if (empty($config['name']) || empty($config['user'])) {
            throw new RuntimeException('Configuração do banco de dados incompleta. Verifique o arquivo .env.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        self::$connection = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
