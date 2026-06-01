<?php

declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die('Erro fatal: Este sistema requer o PHP 8.0 ou superior. Sua versão atual é: ' . PHP_VERSION . '. Por favor, atualize o PHP no painel da sua hospedagem.');
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use App\Core\Config;
use App\Core\Env;
use App\Security\Session;

Env::load(BASE_PATH . '/.env');

$app = Config::app();
$debug = (bool) $app['debug'];

if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

set_exception_handler(function (\Throwable $e) use ($debug): void {
    http_response_code(500);
    log_app_exception($e);

    if (expects_json_response()) {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => $debug ? $e->getMessage() : 'Erro interno do sistema.',
            'errors' => $debug ? [
                'type' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ] : [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($debug) {
        echo '<h1>Erro Crítico no Sistema</h1>';
        echo '<p><strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        echo '<p><strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' na linha ' . (int) $e->getLine() . '</p>';
        echo "<pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        exit;
    }

    echo '<h1>Erro interno</h1>';
    echo '<p>Não foi possível concluir a solicitação. Tente novamente mais tarde.</p>';
    exit;
});

register_shutdown_function(function (): void {
    $error = error_get_last();

    if (!$error || !in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    log_app_error($error);
});

function expects_json_response(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $requestUri = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '');

    return str_contains($accept, 'application/json')
        || str_contains($script, '/api/')
        || str_contains($requestUri, '/api/');
}

function log_app_exception(\Throwable $e): void
{
    log_app_message(sprintf(
        "[%s] %s: %s in %s:%d\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
}

function log_app_error(array $error): void
{
    log_app_message(sprintf(
        "[%s] PHP error %d: %s in %s:%d\n\n",
        date('Y-m-d H:i:s'),
        (int)($error['type'] ?? 0),
        (string)($error['message'] ?? 'Erro fatal'),
        (string)($error['file'] ?? 'arquivo desconhecido'),
        (int)($error['line'] ?? 0)
    ));
}

function log_app_message(string $message): void
{
    $logDir = BASE_PATH . '/storage/logs';
    $logFile = $logDir . '/app.log';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    if (!is_dir($logDir) || !is_writable($logDir)) {
        return;
    }

    @file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
}

// Inicia sessão automaticamente
Session::start();

/**
 * Escapa HTML para output seguro nos templates.
 * Uso: <?= e($variavel) ?>
 */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
