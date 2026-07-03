<?php

declare(strict_types=1);

$required = ['DB_TEST_HOST', 'DB_TEST_PORT', 'DB_TEST_NAME', 'DB_TEST_USER', 'DB_TEST_PASSWORD'];

foreach ($required as $key) {
    if (getenv($key) === false || trim((string) getenv($key)) === '') {
        echo 'SKIP database-integration-test: DB_TEST_* não configurado.' . PHP_EOL;
        exit(0);
    }
}

if (getenv('DB_NAME') !== false && getenv('DB_TEST_NAME') === getenv('DB_NAME')) {
    echo 'SKIP database-integration-test: banco de teste igual ao banco principal.' . PHP_EOL;
    exit(0);
}

if (!str_ends_with((string) getenv('DB_TEST_NAME'), '_test')) {
    echo 'SKIP database-integration-test: DB_TEST_NAME deve terminar com _test.' . PHP_EOL;
    exit(0);
}

echo 'SKIP database-integration-test: suite de integração preparada; execução real depende de banco de teste isolado.' . PHP_EOL;
