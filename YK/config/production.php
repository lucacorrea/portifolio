<?php
declare(strict_types=1);

return [
    'environment' => getenv('YK_APP_ENV') ?: 'production',
    'error_log' => dirname(__DIR__) . '/storage/logs/app.log',
];
