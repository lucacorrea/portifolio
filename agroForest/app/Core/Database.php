<?php
class Database
{
    private static ?PDO $pdo = null;
    private static ?array $lastConfig = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = self::config();
        self::$lastConfig = $config;

        try {
            self::$pdo = self::connect($config);
        } catch (PDOException $exception) {
            $fallbacks = self::fallbackConfigs($config);

            foreach ($fallbacks as $fallbackConfig) {
                try {
                    self::$pdo = self::connect($fallbackConfig);
                    self::$lastConfig = $fallbackConfig;
                    AppLogger::info('PDO connected using fallback: ' . self::safeContext($fallbackConfig));
                    return self::$pdo;
                } catch (PDOException $fallbackException) {
                    AppLogger::error('PDO fallback failed: ' . self::safeContext($fallbackConfig), $fallbackException);
                }
            }

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
            'host' => trim((string) ($config['host'] ?? 'localhost')),
            'port' => trim((string) ($config['port'] ?? '3306')),
            'database' => trim((string) ($config['database'] ?? '')),
            'username' => trim((string) ($config['username'] ?? '')),
            'password' => $config['password'] ?? '',
            'charset' => trim((string) ($config['charset'] ?? 'utf8mb4')),
        ];
    }

    public static function activeContext(): string
    {
        return self::safeContext(self::$lastConfig ?? self::config());
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

    private static function fallbackConfigs(array $config): array
    {
        $host = $config['host'] ?? '';
        $fallbacks = [];

        if ($host === 'localhost') {
            $fallbacks[] = array_replace($config, ['host' => '127.0.0.1']);
        }

        if ($host === '127.0.0.1') {
            $fallbacks[] = array_replace($config, ['host' => 'localhost']);
        }

        return $fallbacks;
    }

    private static function connect(array $config): PDO
    {
        return new PDO(self::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
