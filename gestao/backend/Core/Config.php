<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    public static function app(): array
    {
        return [
            'name' => (string) Env::first(['APP_NAME'], 'Sistema de Gestão'),
            'env' => (string) Env::first(['APP_ENV'], 'production'),
            'debug' => self::boolFromKeys(['APP_DEBUG'], false),
            'url' => rtrim((string) Env::first(['BASE_URL', 'APP_URL'], self::detectBaseUrl()), '/'),
        ];
    }

    public static function database(): array
    {
        return [
            'host' => (string) Env::first(['DB_HOST'], 'localhost'),
            'port' => (int) Env::first(['DB_PORT'], 3306),
            'name' => (string) Env::first(['DB_DATABASE', 'DB_NAME'], ''),
            'user' => (string) Env::first(['DB_USERNAME', 'DB_USER'], ''),
            'pass' => (string) Env::first(['DB_PASSWORD', 'DB_PASS'], ''),
            'charset' => (string) Env::first(['DB_CHARSET'], 'utf8mb4'),
        ];
    }

    public static function session(): array
    {
        return [
            'name' => (string) Env::first(['SESSION_NAME'], 'GESTAO_SESSION'),
            'secure' => self::boolFromKeys(['SESSION_SECURE'], self::isHttps()),
            'lifetime' => (int) Env::first(['SESSION_LIFETIME'], 7200),
        ];
    }

    private static function boolFromKeys(array $keys, bool $default): bool
    {
        $value = Env::first($keys, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private static function detectBaseUrl(): string
    {
        $scheme = self::isHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = $scriptDir === '/' || $scriptDir === '.' ? '' : $scriptDir;

        return $scheme . '://' . $host . $scriptDir;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
