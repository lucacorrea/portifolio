<?php

declare(strict_types=1);

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path) ?: [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = $path . DIRECTORY_SEPARATOR . $item;

        is_dir($full) ? remove_tree($full) : @unlink($full);
    }

    @rmdir($path);
}

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigas_bootstrap_test_' . bin2hex(random_bytes(6));
$private = $base . DIRECTORY_SEPARATOR . 'configuracao' . DIRECTORY_SEPARATOR . 'sigas';
$envDir = $private . DIRECTORY_SEPARATOR . 'conect';
$imageRoot = $private . DIRECTORY_SEPARATOR . 'uplouds' . DIRECTORY_SEPARATOR . 'img';
$documentRoot = $private . DIRECTORY_SEPARATOR . 'uplouds' . DIRECTORY_SEPARATOR . 'document';
$logRoot = $private . DIRECTORY_SEPARATOR . 'logs';
mkdir($envDir, 0750, true);
mkdir($imageRoot, 0750, true);
mkdir($documentRoot, 0750, true);
mkdir($logRoot, 0750, true);

$envFile = $envDir . DIRECTORY_SEPARATOR . '.env';
file_put_contents($envFile, implode(PHP_EOL, [
    'APP_NAME="SIGAS Teste"',
    'APP_ENV=production',
    'APP_DEBUG=false',
    'APP_URL=https://exemplo.gov.br/sigas',
    'APP_TIMEZONE=America/Manaus',
    'DB_HOST=db-hospedagem',
    'DB_PORT=3306',
    'DB_NAME=sigas_teste',
    'DB_USER=usuario_teste',
    'DB_PASSWORD=senha_teste',
    'DB_CHARSET=utf8mb4',
    'SESSION_NAME=SIGAS_TEST_SESSION',
    'SESSION_LIFETIME=7200',
    'SESSION_IDLE_TIMEOUT=1800',
    'SESSION_COOKIE_PATH=/sigas',
    'TRUST_PROXY_HEADERS=false',
    'PRIVATE_BASE_PATH=' . $private,
    'SIGAS_IMAGE_PATH=' . $imageRoot,
    'SIGAS_DOCUMENT_PATH=' . $documentRoot,
    'SIGAS_LOG_PATH=' . $logRoot,
    'MAX_IMAGE_SIZE=5242880',
    'MAX_DOCUMENT_SIZE=10485760',
    'INSTALLATION_ENABLED=false',
    'INSTALLATION_KEY=chave_teste',
    'INSTALLATION_LOCK_PATH=' . $private . DIRECTORY_SEPARATOR . 'installation.lock',
]));

putenv('SIGAS_ENV_PATH=' . $envFile);

require dirname(__DIR__) . '/bootstrap.php';

assert_true(class_exists(App\Core\Environment::class), 'autoloader carregado');
assert_true(App\Core\Environment::get('APP_NAME') === 'SIGAS Teste', 'configuracoes carregadas');
assert_true(date_default_timezone_get() === 'America/Manaus', 'timezone aplicado');
assert_true(App\Core\Environment::bool('APP_DEBUG') === false, 'debug de producao desativado');

$reflection = new ReflectionClass(App\Core\Database::class);
$property = $reflection->getProperty('connection');
$property->setAccessible(true);
assert_true($property->getValue() === null, 'PDO lazy sem conexao automatica');

$missingEnvThrown = false;

try {
    App\Core\Environment::load($base . DIRECTORY_SEPARATOR . 'ausente.env');
} catch (Throwable) {
    $missingEnvThrown = true;
}

assert_true($missingEnvThrown, 'ausencia do env falha com excecao segura');

@unlink($envFile);
remove_tree($base);

echo $failures === 0 ? 'PASS bootstrap-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
