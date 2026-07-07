<?php

declare(strict_types=1);

namespace App\Integrations\Anexo;

use JsonSerializable;
use RuntimeException;

final readonly class AnexoDatabaseConfig implements JsonSerializable
{
    private function __construct(
        private bool $enabled,
        private string $host,
        private int $port,
        private string $database,
        private string $username,
        private string $password,
        private string $charset,
        private int $timeout,
    ) {
    }

    public static function disabled(): self
    {
        return new self(false, '', 3306, '', '', '', 'utf8mb4', 3);
    }

    public static function fromEnvironment(AnexoEnvironment $environment): self
    {
        if (!$environment->bool('ANEXO_INTEGRATION_ENABLED', false)) {
            return self::disabled();
        }

        $config = new self(
            true,
            trim($environment->required('ANEXO_DB_HOST')),
            $environment->int('ANEXO_DB_PORT', 3306),
            trim($environment->required('ANEXO_DB_NAME')),
            trim($environment->required('ANEXO_DB_USER')),
            $environment->required('ANEXO_DB_PASS'),
            trim((string) $environment->get('ANEXO_DB_CHARSET', 'utf8mb4')),
            $environment->int('ANEXO_DB_TIMEOUT', 3),
        );
        $config->validate();

        return $config;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /** @return array<string,mixed> */
    public function __debugInfo(): array
    {
        return [
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => '[redacted]',
            'charset' => $this->charset,
            'timeout' => $this->timeout,
        ];
    }

    private function validate(): void
    {
        if ($this->host === '' || $this->database === '' || $this->username === '') {
            throw new RuntimeException('Invalid ANEXO database configuration.');
        }
        if ($this->port < 1 || $this->port > 65535) {
            throw new RuntimeException('Invalid ANEXO database port.');
        }
        if (!in_array($this->charset, ['utf8mb4', 'utf8'], true)) {
            throw new RuntimeException('Invalid ANEXO database charset.');
        }
        if ($this->timeout < 1 || $this->timeout > 10) {
            throw new RuntimeException('Invalid ANEXO database timeout.');
        }
    }
}
