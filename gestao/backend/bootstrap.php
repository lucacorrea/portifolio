<?php

declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die('Erro fatal: Este sistema requer o PHP 8.0 ou superior. Sua versão atual é: ' . PHP_VERSION . '. Por favor, atualize o PHP no painel da sua hospedagem.');
}

set_exception_handler(function (\Throwable $e) {
    http_response_code(500);
    echo "<h1>Erro Crítico no Sistema</h1>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . " na linha " . $e->getLine() . "</p>";
    echo "<!--\n" . $e->getTraceAsString() . "\n-->";
    exit;
});

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

if ($app['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
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
