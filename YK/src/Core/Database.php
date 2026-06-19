<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly array $config,
        private readonly string $environment = 'production',
        private readonly ?string $logFile = null
    ) {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Driver de banco indisponivel.');
        }

        $host = $this->requiredString('host');
        $database = $this->requiredString('database');
        $username = $this->requiredString('username');
        $password = (string) ($this->config['password'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $port = (int) ($this->config['port'] ?? 3306);

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
            $charset = 'utf8mb4';
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);

            return $this->connection;
        } catch (PDOException $exception) {
            $this->logFailure($exception);

            throw new RuntimeException('Nao foi possivel concluir a operacao.');
        }
    }

    private function requiredString(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        if ($value === '') {
            throw new RuntimeException('Configuracao de banco incompleta.');
        }

        return $value;
    }

    private function logFailure(Throwable $exception): void
    {
        $message = sprintf(
            '[%s] Database connection failed: %s',
            date('c'),
            $exception->getMessage()
        );

        if ($this->logFile !== null) {
            $dir = dirname($this->logFile);
            if (is_dir($dir) && is_writable($dir)) {
                error_log($message . PHP_EOL, 3, $this->logFile);
                return;
            }
        }

        if ($this->environment !== 'production') {
            error_log($message);
        }
    }
}
