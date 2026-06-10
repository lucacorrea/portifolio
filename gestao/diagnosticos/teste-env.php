<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

require_once dirname(__DIR__) . '/config/env.php';

$keys = [
    'APP_ENV',
    'APP_DEBUG',
    'BASE_URL',
    'APP_URL',
    'DB_HOST',
    'DB_DATABASE',
    'DB_NAME',
    'DB_USERNAME',
    'DB_USER',
    'DB_PASSWORD',
    'DB_PASS',
    'DB_PORT',
];

echo ".env carregado: " . (App\Core\Env::loadedPath() ? 'sim' : 'nao') . "\n";

foreach ($keys as $key) {
    $value = env($key);
    echo $key . ': ' . (($value === null || $value === '') ? 'nao definido' : 'definido') . "\n";
}
