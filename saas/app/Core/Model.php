<?php
declare(strict_types=1);

abstract class Model
{
    private static ?PDO $pdo = null;

    protected static function db(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require APP_PATH . '/Config/database.php';

        $driver   = $config['driver']   ?? 'mysql';
        $host     = $config['host']     ?? '127.0.0.1';
        $port     = (int)($config['port'] ?? 3306);
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $charset  = $config['charset']  ?? 'utf8mb4';

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $driver,
            $host,
            $port,
            $database,
            $charset
        );

        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }
}