<?php

declare(strict_types=1);

class SigasDatabaseConfig
{
    private $enabled;
    private $host;
    private $port;
    private $database;
    private $username;
    private $password;
    private $charset;
    private $timeout;

    private function __construct(
        $enabled,
        $host,
        $port,
        $database,
        $username,
        $password,
        $charset,
        $timeout
    ) {
        $this->enabled = (bool)$enabled;
        $this->host = (string)$host;
        $this->port = (int)$port;
        $this->database = (string)$database;
        $this->username = (string)$username;
        $this->password = (string)$password;
        $this->charset = (string)$charset;
        $this->timeout = (int)$timeout;
    }

    public static function disabled()
    {
        return new self(
            false,
            '',
            3306,
            '',
            '',
            '',
            'utf8mb4',
            5
        );
    }

    public static function fromEnvironment(SigasEnvironment $environment)
    {
        if (!$environment->bool('SIGAS_INTEGRATION_ENABLED', false)) {
            return self::disabled();
        }

        $config = new self(
            true,
            trim($environment->required('SIGAS_DB_HOST')),
            $environment->int('SIGAS_DB_PORT', 3306),
            trim($environment->required('SIGAS_DB_NAME')),
            trim($environment->required('SIGAS_DB_USER')),
            $environment->required('SIGAS_DB_PASS'),
            trim((string)$environment->get(
                'SIGAS_DB_CHARSET',
                'utf8mb4'
            )),
            $environment->int('SIGAS_DB_TIMEOUT', 5)
        );

        $config->validate();

        return $config;
    }

    public function enabled()
    {
        return $this->enabled;
    }

    public function dsn()
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    public function username()
    {
        return $this->username;
    }

    public function password()
    {
        return $this->password;
    }

    public function database()
    {
        return $this->database;
    }

    public function timeout()
    {
        return $this->timeout;
    }

    private function validate()
    {
        if (
            $this->host === ''
            || $this->database === ''
            || $this->username === ''
        ) {
            throw new RuntimeException(
                'Configuração de banco SIGAS incompleta.'
            );
        }

        if ($this->database !== 'u784961086_sigas') {
            throw new RuntimeException(
                'O banco configurado não é o banco oficial do SIGAS.'
            );
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new RuntimeException(
                'Porta inválida na integração SIGAS.'
            );
        }

        if (
            !in_array(
                $this->charset,
                array('utf8mb4', 'utf8'),
                true
            )
        ) {
            throw new RuntimeException(
                'Charset inválido na integração SIGAS.'
            );
        }

        if ($this->timeout < 1 || $this->timeout > 10) {
            throw new RuntimeException(
                'Timeout inválido na integração SIGAS.'
            );
        }
    }
}
