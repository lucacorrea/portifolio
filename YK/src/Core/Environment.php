<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Environment
{
    private const ALLOWED_KEYS = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_TIMEZONE',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_CHARSET',
        'SESSION_NAME',
        'SESSION_TIMEOUT',
        'SESSION_ABSOLUTE_TIMEOUT',
        'SESSION_REGENERATE_INTERVAL',
        'SESSION_COOKIE_PATH',
        'LOGIN_MAX_ATTEMPTS',
        'LOGIN_LOCK_MINUTES',
    ];

    public function __construct(private string $filePath)
    {
    }

    public static function resolveFilePath(string $projectRoot): string
    {
        $configuredPath = getenv('YK_ENV_PATH');
        if (is_string($configuredPath) && trim($configuredPath) !== '') {
            return trim($configuredPath);
        }

        return dirname($projectRoot)
            . DIRECTORY_SEPARATOR
            . 'configuracoes'
            . DIRECTORY_SEPARATOR
            . 'YK'
            . DIRECTORY_SEPARATOR
            . '.env';
    }

    public function load(): void
    {
        if (!is_file($this->filePath) || !is_readable($this->filePath)) {
            throw new RuntimeException('Arquivo de ambiente ausente ou inacessivel.');
        }

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Nao foi possivel carregar o arquivo de ambiente.');
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }

            if (getenv($key) !== false) {
                continue;
            }

            $value = $this->normalizeValue($value);

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $this->assertAllowedKey($key);

        $value = getenv($key);

        return $value === false ? $default : (string) $value;
    }

    public function require(string $key): string
    {
        $value = $this->get($key);

        if ($value === null || trim($value) === '') {
            throw new RuntimeException('Variavel de ambiente obrigatoria ausente.');
        }

        return $value;
    }

    private function normalizeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $quote = $value[0];
        $last = substr($value, -1);

        if (($quote === '"' || $quote === "'") && $last === $quote) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function assertAllowedKey(string $key): void
    {
        if (!in_array($key, self::ALLOWED_KEYS, true)) {
            throw new RuntimeException('Variavel de ambiente nao permitida.');
        }
    }
}
