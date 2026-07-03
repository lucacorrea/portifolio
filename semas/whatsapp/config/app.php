<?php
declare(strict_types=1);

return [
    'env' => semas_whatsapp_env('APP_ENV', 'production'),
    'url' => rtrim((string)semas_whatsapp_env('APP_URL', ''), '/'),
    'timezone' => semas_whatsapp_env('APP_TIMEZONE', 'America/Manaus'),
    'instance_id' => semas_whatsapp_env('WHATSAPP_INSTANCE_ID', 'semas_whatsapp'),
];
