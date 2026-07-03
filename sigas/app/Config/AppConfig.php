<?php

declare(strict_types=1);

namespace App\Config;

use App\Core\Environment;

final class AppConfig
{
    public static function name(): string
    {
        return (string) Environment::get('APP_NAME', 'SIGAS');
    }

    public static function environment(): string
    {
        return (string) Environment::get('APP_ENV', 'production');
    }

    public static function debug(): bool
    {
        return Environment::bool('APP_DEBUG', false);
    }

    public static function url(): string
    {
        return rtrim((string) Environment::get('APP_URL', ''), '/');
    }

    public static function timezone(): string
    {
        return (string) Environment::get('APP_TIMEZONE', 'America/Manaus');
    }
}
