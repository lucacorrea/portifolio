<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    public static function app(): array
    {
        return [
            'name' => Env::get('APP_NAME', 'L&J Caixa'),
            'env' => Env::get('APP_ENV', 'production'),
            'debug' => Env::bool('APP_DEBUG', false),
            'url' => Env::get('APP_URL', ''),
        ];
    }

    public static function database(): array
    {
        return [
            'host' => Env::get('DB_HOST', 'localhost'),
            'port' => Env::int('DB_PORT', 3306),
            'name' => Env::get('DB_NAME', ''),
            'user' => Env::get('DB_USER', ''),
            'pass' => Env::get('DB_PASS', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    public static function session(): array
    {
        return [
            'name' => Env::get('SESSION_NAME', 'LJ_CAIXA_SESSION'),
            'secure' => Env::bool('SESSION_SECURE', true),
            'lifetime' => Env::int('SESSION_LIFETIME', 7200),
        ];
    }
}
