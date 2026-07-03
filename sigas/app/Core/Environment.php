<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Environment
{
    /** @var array<string, mixed> */
    private static array $values = [];

    public static function locate(): string
    {
        $paths = [];
        $serverEnvPath = getenv('SIGAS_ENV_PATH');

        if (is_string($serverEnvPath) && trim($serverEnvPath) !== '') {
            $paths[] = trim($serverEnvPath);
        }

        if (defined('SIGAS_ENV_PATH')) {
            $constantEnvPath = constant('SIGAS_ENV_PATH');

            if (is_string($constantEnvPath) && trim($constantEnvPath) !== '') {
                $paths[] = trim($constantEnvPath);
            }
        }

        $homePath = getenv('HOME');

        if (is_string($homePath) && trim($homePath) !== '') {
            $paths[] = rtrim($homePath, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'configuracao'
                . DIRECTORY_SEPARATOR . 'sigas'
                . DIRECTORY_SEPARATOR . 'conect'
                . DIRECTORY_SEPARATOR . '.env';
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $paths[] = dirname((string) $_SERVER['DOCUMENT_ROOT'])
                . DIRECTORY_SEPARATOR . 'configuracao'
                . DIRECTORY_SEPARATOR . 'sigas'
                . DIRECTORY_SEPARATOR . 'conect'
                . DIRECTORY_SEPARATOR . '.env';
        }

        foreach (array_unique($paths) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Environment file was not found or is not readable.');
    }

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Environment file was not found or is not readable.');
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Environment file could not be read.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\R/', $content) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $rawValue = trim(substr($line, $separator + 1));

            self::assertValidKey($key);

            $serverValue = getenv($key);

            if ($serverValue !== false) {
                self::$values[$key] = $serverValue;
                continue;
            }

            $value = self::normalizeValue($rawValue);
            self::$values[$key] = $value;

            if (is_scalar($value) || $value === null) {
                putenv($key . '=' . (string) $value);
                $_ENV[$key] = $value;
            }
        }
    }

    public static function has(string $key): bool
    {
        self::assertValidKey($key);

        return array_key_exists($key, self::$values) || getenv($key) !== false;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::assertValidKey($key);

        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $serverValue = getenv($key);

        return $serverValue === false ? $default : $serverValue;
    }

    public static function required(string $key): string
    {
        $value = self::get($key);

        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException('Required environment variable is missing.');
        }

        return (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

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

        $lower = strtolower($value);

        return match ($lower) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }

    private static function assertValidKey(string $key): void
    {
        if ($key === '' || preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
            throw new RuntimeException('Invalid environment variable name.');
        }
    }
}
