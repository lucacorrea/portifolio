<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset = 'utf8mb4',
        private readonly ?string $timezone = null
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

        $host = $this->requiredString($this->host);
        $database = $this->requiredString($this->database);
        $username = $this->requiredString($this->username);
        $password = $this->password;
        $charset = $this->charset;
        $port = $this->port;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
            $charset = 'utf8mb4';
        }

        if ($port <= 0 || $port > 65535) {
            throw new RuntimeException('Porta de banco invalida.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        try {
            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]
            );

            $this->configureSessionTimezone($this->connection);

            return $this->connection;
        } catch (PDOException $exception) {
            $this->logFailure();

            throw new RuntimeException(
                'Nao foi possivel concluir a operacao.',
                0,
                $exception
            );
        }
    }

    private function configureSessionTimezone(PDO $connection): void
    {
        $timezoneName = trim((string) ($this->timezone ?? ''));

        if ($timezoneName === '') {
            $timezoneName = date_default_timezone_get();
        }

        try {
            $timezone = new DateTimeZone($timezoneName);
            $offset = (new DateTimeImmutable('now', $timezone))->format('P');
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Fuso horario da aplicacao invalido.',
                0,
                $exception
            );
        }

        if (!preg_match('/^[+-](?:0\\d|1\\d|2[0-3]):[0-5]\\d$/', $offset)) {
            throw new RuntimeException('Offset de fuso horario invalido.');
        }

        $connection->exec("SET SESSION time_zone = '" . $offset . "'");
    }

    private function requiredString(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new RuntimeException('Configuracao de banco incompleta.');
        }

        return $value;
    }

    private function logFailure(): void
    {
        error_log('Database connection failed.');
    }
}
