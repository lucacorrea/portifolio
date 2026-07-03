<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    private const PREFIX = 'App\\';

    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, self::PREFIX)) {
                return;
            }

            $relative = substr($class, strlen(self::PREFIX));
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative)
                . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
