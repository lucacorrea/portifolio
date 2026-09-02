<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'env' => (string) env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'timezone' => (string) env('APP_TIMEZONE', 'America/Manaus'),
    'session' => [
        'name' => (string) env('SESSION_NAME', 'BIAUTOSESSID'),
        'lifetime' => max(900, (int) env('SESSION_LIFETIME', 7200)),
    ],
    'auth' => [
        'max_attempts' => max(3, (int) env('AUTH_MAX_ATTEMPTS', 5)),
        'lock_minutes' => max(1, (int) env('AUTH_LOCK_MINUTES', 15)),
    ],
];
