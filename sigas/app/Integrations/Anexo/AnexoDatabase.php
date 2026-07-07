<?php

declare(strict_types=1);

namespace App\Integrations\Anexo;

use App\Core\Logger;
use PDO;
use PDOException;

final class AnexoDatabase
{
    private ?PDO $connection = null;

    public function __construct(private readonly AnexoDatabaseConfig $config)
    {
    }

    public function connection(): PDO
    {
        if (!$this->config->enabled()) {
            throw new AnexoUnavailableException('ANEXO integration is disabled.');
        }

        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $this->connection = new PDO(
                $this->config->dsn(),
                $this->config->username(),
                $this->config->password(),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_TIMEOUT => $this->config->timeout(),
                ]
            );
        } catch (PDOException $exception) {
            Logger::application('ANEXO database connection failed.', [
                'type' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            throw new AnexoUnavailableException('ANEXO database is unavailable.', 0, $exception);
        }

        return $this->connection;
    }
}
