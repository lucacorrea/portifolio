<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Environment;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));

    if ($relativeClass === '' || str_contains($relativeClass, '..') || str_contains($relativeClass, "\0")) {
        throw new RuntimeException('Classe invalida para autoload.');
    }

    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    $file = $baseDir . $relativePath;
    $realBase = realpath($baseDir);
    $realFile = is_file($file) ? realpath($file) : false;

    if ($realBase === false || $realFile === false || strncmp($realFile, $realBase, strlen($realBase)) !== 0) {
        if ((getenv('APP_ENV') ?: 'production') !== 'production') {
            throw new RuntimeException('Classe nao encontrada: ' . $class);
        }

        return;
    }

    require $realFile;
});

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/app.log');
error_reporting(E_ALL);

try {
    $environment = new Environment(Environment::resolveFilePath(__DIR__));
    $environment->load();

    $appEnv = $environment->require('APP_ENV');
    $appDebug = filter_var($environment->require('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
    $timezone = $environment->require('APP_TIMEZONE');

    date_default_timezone_set($timezone);

    ini_set('display_errors', $appEnv === 'production' || !$appDebug ? '0' : '1');
    ini_set('display_startup_errors', $appEnv === 'production' || !$appDebug ? '0' : '1');
    ini_set('log_errors', '1');

    $port = filter_var($environment->require('DB_PORT'), FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 65535,
        ],
    ]);

    if ($port === false) {
        throw new RuntimeException('Porta de banco invalida.');
    }

    return [
        'environment' => $environment,
        'database' => new Database(
            host: $environment->require('DB_HOST'),
            port: $port,
            database: $environment->require('DB_DATABASE'),
            username: $environment->require('DB_USERNAME'),
            password: $environment->require('DB_PASSWORD'),
            charset: $environment->require('DB_CHARSET')
        ),
    ];
} catch (Throwable $exception) {
    error_log('Bootstrap failed: ' . $exception->getMessage());

    if (PHP_SAPI === 'cli') {
        throw new RuntimeException('Configuracao do ambiente invalida.');
    }

    http_response_code(500);
    exit('Não foi possível inicializar o sistema. Entre em contato com o administrador.');
}
