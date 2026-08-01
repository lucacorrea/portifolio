<?php
declare(strict_types=1);
namespace App\Integration\SO;
use PDO;
use PDOException;
final class SoDatabase
{
    private ?PDO $connection = null;
    public function __construct(private readonly string $projectRoot) {}
    public function connection(): PDO
    {
        if ($this->connection !== null) return $this->connection;
        try {
            $env = new SoEnvironment($this->projectRoot);
            $port = filter_var($env->get('DB_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if ($port === false) throw new SoIntegrationException(reason: 'configuration_invalid');
            $host = $env->get('DB_HOST');
            $dsn = 'mysql:host=' . $host;
            if (strtolower($host) !== 'localhost') {
                $dsn .= ';port=' . $port;
            }
            $dsn .= ';dbname=' . $env->get('DB_DATABASE') . ';charset=' . $env->get('DB_CHARSET');

            return $this->connection = new PDO($dsn, $env->get('DB_USERNAME'), $env->get('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => false, PDO::ATTR_TIMEOUT => 3]);
        } catch (PDOException|SoIntegrationException $exception) {
            $nativeCode = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
            error_log('SO integration connection failed. type=' . $exception::class . ' code=' . $exception->getCode() . ' native=' . $nativeCode . '.');
            if ($exception instanceof SoIntegrationException) throw $exception;
            throw new SoIntegrationException(reason: 'database_connection_failed', previous: $exception);
        }
    }
}
