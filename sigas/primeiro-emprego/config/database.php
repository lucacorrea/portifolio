<?php

declare(strict_types=1);

/*
 * Configuração sem credenciais fixas no código.
 * 1) Se o sistema principal já expõe $GLOBALS['pdo'], ele será reutilizado.
 * 2) Caso contrário, configure as variáveis de ambiente abaixo.
 */
return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'name' => getenv('DB_NAME') ?: '',
    'user' => getenv('DB_USER') ?: '',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];
