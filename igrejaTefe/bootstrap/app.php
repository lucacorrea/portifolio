<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Router;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (is_file($autoloadPath)) {
    require $autoloadPath;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $normalized = strtolower((string) $value);

        return match ($normalized) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => trim((string) $value, "\"'"),
        };
    }
}

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '' || array_key_exists($key, $_ENV)) {
                continue;
            }

            $_ENV[$key] = trim($value, "\"'");
            $_SERVER[$key] = $_ENV[$key];
        }
    }
}

load_env_file(BASE_PATH . '/.env');

Config::load([
    'app' => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
    'security' => require BASE_PATH . '/config/security.php',
]);

date_default_timezone_set(Config::get('app.timezone', 'UTC'));
Session::configure(Config::get('security.session', []));

$router = new Router();

require BASE_PATH . '/routes/web.php';

return $router;

