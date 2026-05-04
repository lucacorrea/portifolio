<?php
return [
    'driver' => 'mysql',
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => '3306',
    'database' => getenv('DB_DATABASE') ?: 'u784961086_agro',
    'username' => getenv('DB_USERNAME') ?: 'u784961086_agro',
    'password' => getenv('DB_PASSWORD') ?: '=e@;gw+6>F6',
    'charset' => 'utf8mb4',
];
