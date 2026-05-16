<?php

namespace FluxEmpresa\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = env('DB_HOST');
        $port = env('DB_PORT', '3306');
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $charset = env('DB_CHARSET', 'utf8mb4');

        if (!$host || !$database || !$username) {
            throw new RuntimeException('Configuração de banco incompleta. Verifique o arquivo .env.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, (string) $username, (string) $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return self::$connection;
        } catch (PDOException $exception) {
            error_log('FluxEmpresa database connection failed: ' . $exception->getMessage());
            throw new RuntimeException('Não foi possível conectar ao banco de dados. Verifique o arquivo .env.');
        }
    }
}
