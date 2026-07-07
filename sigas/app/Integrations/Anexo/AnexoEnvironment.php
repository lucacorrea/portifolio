<?php

declare(strict_types=1);

namespace App\Integrations\Anexo;

use RuntimeException;

final class AnexoEnvironment
{
    /** @param array<string,mixed> $values */
    private function __construct(private array $values)
    {
    }

    public static function locate(): ?string
    {
        $paths = [];
        $serverPath = $_SERVER['ANEXO_ENV_PATH'] ?? null;

        if (is_string($serverPath) && trim($serverPath) !== '') {
            $paths[] = trim($serverPath);
        }

        $envPath = getenv('ANEXO_ENV_PATH');
        if (is_string($envPath) && trim($envPath) !== '') {
            $paths[] = trim($envPath);
        }

        $homePath = getenv('HOME');
        if (is_string($homePath) && trim($homePath) !== '') {
            $paths[] = rtrim($homePath, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'configuracao'
                . DIRECTORY_SEPARATOR . 'anexo'
                . DIRECTORY_SEPARATOR . 'conect'
                . DIRECTORY_SEPARATOR . '.env';
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $paths[] = dirname((string) $_SERVER['DOCUMENT_ROOT'])
                . DIRECTORY_SEPARATOR . 'configuracao'
                . DIRECTORY_SEPARATOR . 'anexo'
                . DIRECTORY_SEPARATOR . 'conect'
                . DIRECTORY_SEPARATOR . '.env';
        }

        foreach (array_unique($paths) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function load(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('ANEXO environment file is not readable.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('ANEXO environment file could not be read.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $values = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            if (preg_match('/^ANEXO_[A-Z0-9_]+$/', $key) !== 1) {
                continue;
            }

            $values[$key] = self::normalizeValue(trim(substr($line, $separator + 1)));
        }

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertAnexoKey($key);

        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function required(string $key): string
    {
        $value = $this->get($key);

        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException('Required ANEXO environment variable is missing.');
        }

        return (string) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        if (is_int($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_INT) === false ? $default : (int) $value;
    }

    private static function normalizeValue(string $value): mixed
    {
        if (
            strlen($value) >= 2
            && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }

    private function assertAnexoKey(string $key): void
    {
        if (preg_match('/^ANEXO_[A-Z0-9_]+$/', $key) !== 1) {
            throw new RuntimeException('Invalid ANEXO environment variable name.');
        }
    }
}
