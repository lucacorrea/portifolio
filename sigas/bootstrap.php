<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Environment;
use App\Core\Logger;
use App\Core\Session;

if (defined('SIGAS_BOOTSTRAPPED')) {
    return;
}

define('SIGAS_BOOTSTRAPPED', true);

try {
    require_once __DIR__ . '/app/Core/Autoloader.php';

    Autoloader::register();

    $envPath = Environment::locate();
    Environment::load($envPath);

    date_default_timezone_set((string) Environment::get('APP_TIMEZONE', 'America/Manaus'));

    $debug = Environment::bool('APP_DEBUG', false);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('display_startup_errors', $debug ? '1' : '0');
    error_reporting(E_ALL);

    Logger::configure((string) Environment::get('SIGAS_LOG_PATH', ''));

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $exception): void {
        Logger::application('Unhandled application exception.', [
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'O sistema não pôde ser inicializado.' . PHP_EOL);
            exit(1);
        }

        http_response_code(500);
        echo 'O sistema não pôde ser inicializado.';
        exit;
    });

    Session::configure();
    Session::start();
} catch (Throwable $exception) {
    if (class_exists(Logger::class, false)) {
        Logger::application('Bootstrap failed.', [
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);
    } else {
        error_log('SIGAS bootstrap failed.');
    }

    if (PHP_SAPI === 'cli') {
        throw $exception;
    }

    http_response_code(500);
    echo 'O sistema não pôde ser inicializado.';
    exit;
}
