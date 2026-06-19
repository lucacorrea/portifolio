<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

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
        if ((getenv('YK_APP_ENV') ?: 'production') !== 'production') {
            throw new RuntimeException('Classe nao encontrada: ' . $class);
        }

        return;
    }

    require $realFile;
});

date_default_timezone_set('America/Manaus');

$productionConfig = require __DIR__ . '/config/production.php';
$databaseConfig = require __DIR__ . '/config/database.php';

$environment = (string) ($productionConfig['environment'] ?? 'production');

ini_set('display_errors', $environment === 'production' ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', (string) ($productionConfig['error_log'] ?? (__DIR__ . '/storage/logs/app.log')));
error_reporting(E_ALL);

$config = new Config([
    'app' => [
        'environment' => $environment,
        'timezone' => 'America/Manaus',
    ],
    'database' => $databaseConfig,
    'paths' => [
        'storage' => __DIR__ . '/storage',
        'logs' => __DIR__ . '/storage/logs',
    ],
]);

return [
    'config' => $config,
    'database' => new Database(
        $config->get('database'),
        $environment,
        __DIR__ . '/storage/logs/database.log'
    ),
];
