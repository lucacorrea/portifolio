<?php
declare(strict_types=1);

$config = [
    'host' => getenv('YK_DB_HOST') ?: 'localhost',
    'port' => (int) (getenv('YK_DB_PORT') ?: 3306),
    'database' => getenv('YK_DB_NAME') ?: '',
    'username' => getenv('YK_DB_USER') ?: '',
    'password' => getenv('YK_DB_PASSWORD') ?: '',
    'charset' => getenv('YK_DB_CHARSET') ?: 'utf8mb4',
];

$localFile = __DIR__ . '/database.local.php';
if (is_file($localFile)) {
    $localConfig = require $localFile;

    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

$config['charset'] = $config['charset'] ?: 'utf8mb4';
$config['port'] = (int) ($config['port'] ?: 3306);

return $config;
