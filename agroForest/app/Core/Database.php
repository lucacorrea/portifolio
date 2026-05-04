<?php
class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = self::config();
        $dsn = self::dsn($config);

        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            AppLogger::error('PDO connection failed: ' . self::safeContext($config), $exception);
            throw $exception;
        }

        return self::$pdo;
    }

    public static function config(): array
    {
        $config = require dirname(__DIR__) . '/Config/database.php';

        return [
            'driver' => $config['driver'] ?? 'mysql',
            'host' => $config['host'] ?? 'localhost',
            'port' => (string) ($config['port'] ?? '3306'),
            'database' => $config['database'] ?? '',
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
        ];
    }

    public static function safeContext(?array $config = null): string
    {
        $config ??= self::config();

        return sprintf(
            'driver=%s host=%s port=%s database=%s username=%s pdo_mysql=%s',
            $config['driver'] ?? '',
            $config['host'] ?? '',
            $config['port'] ?? '',
            $config['database'] ?? '',
            $config['username'] ?? '',
            extension_loaded('pdo_mysql') ? 'loaded' : 'missing'
        );
    }

    private static function dsn(array $config): string
    {
        return sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
    }
}
