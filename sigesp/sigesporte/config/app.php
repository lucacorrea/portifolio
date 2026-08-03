<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'SIGESP',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Manaus',
    'session_lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 120),
    'upload_max_size' => (int) (getenv('UPLOAD_MAX_SIZE') ?: 10485760),
];
