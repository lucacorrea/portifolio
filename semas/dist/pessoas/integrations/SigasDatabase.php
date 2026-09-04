<?php

declare(strict_types=1);

class SigasDatabase
{
    private $config;
    private $connection = null;

    public function __construct(SigasDatabaseConfig $config)
    {
        $this->config = $config;
    }

    public function connection()
    {
        if (!$this->config->enabled()) {
            throw new RuntimeException(
                'Integração SIGAS está desabilitada.'
            );
        }

        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $this->connection = new PDO(
                $this->config->dsn(),
                $this->config->username(),
                $this->config->password(),
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_TIMEOUT => $this->config->timeout(),
                )
            );
        } catch (PDOException $exception) {
            @error_log(
                '[SEMAS][SIGAS] Falha PDO. SQLSTATE='
                . $exception->getCode()
            );

            throw new RuntimeException(
                'Banco SIGAS indisponível.',
                0,
                $exception
            );
        }

        return $this->connection;
    }
}
