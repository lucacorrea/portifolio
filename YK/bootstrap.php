<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Environment;
use App\Core\Application;

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

if (!function_exists('action_return_target')) {
    function action_return_target(Application $application, string $default): string
    {
        $fallback = $application->redirect()->sanitize($default);
        $raw = isset($_POST['return_to']) ? (string) $_POST['return_to'] : '';
        $safe = $application->redirect()->sanitize($raw);
        $target = $raw !== '' && $safe !== 'dashboard.php' ? $safe : $fallback;

        $defaultParts = parse_url($fallback);
        if (!isset($defaultParts['query']) || (string) $defaultParts['query'] === '') {
            return $target;
        }

        $targetParts = parse_url($target);
        $path = isset($targetParts['path']) ? (string) $targetParts['path'] : $fallback;
        parse_str(isset($targetParts['query']) ? (string) $targetParts['query'] : '', $query);
        parse_str((string) $defaultParts['query'], $defaultQuery);

        foreach ($defaultQuery as $key => $value) {
            $query[$key] = $value;
        }

        return $path . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

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

    $settings = [
        'app_env' => $appEnv,
        'app_debug' => $appDebug,
        'session_name' => $environment->get('SESSION_NAME', 'YKSESSID'),
        'session_timeout' => (int) $environment->get('SESSION_TIMEOUT', '1800'),
        'session_absolute_timeout' => (int) $environment->get('SESSION_ABSOLUTE_TIMEOUT', '28800'),
        'session_regenerate_interval' => (int) $environment->get('SESSION_REGENERATE_INTERVAL', '900'),
        'session_cookie_path' => $environment->get('SESSION_COOKIE_PATH', '/YK'),
        'login_max_attempts' => (int) $environment->get('LOGIN_MAX_ATTEMPTS', '5'),
        'login_lock_minutes' => (int) $environment->get('LOGIN_LOCK_MINUTES', '15'),
    ];

    $database = new Database(
        host: $environment->require('DB_HOST'),
        port: $port,
        database: $environment->require('DB_DATABASE'),
        username: $environment->require('DB_USERNAME'),
        password: $environment->require('DB_PASSWORD'),
        charset: $environment->require('DB_CHARSET')
    );

    return [
        'environment' => $environment,
        'settings' => $settings,
        'database' => $database,
        'application' => new Application($database, $settings),
    ];
} catch (Throwable $exception) {
    error_log('Bootstrap failed: ' . $exception->getMessage());

    if (PHP_SAPI === 'cli') {
        throw new RuntimeException('Configuracao do ambiente invalida.');
    }

    http_response_code(500);
    exit('Não foi possível inicializar o sistema. Entre em contato com o administrador.');
}
