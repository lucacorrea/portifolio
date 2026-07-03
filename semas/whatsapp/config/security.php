<?php
declare(strict_types=1);

return [
    'session_name' => 'SEMAS_WHATSAPP_SESS',
    'session_timeout' => semas_whatsapp_env_int('SESSION_IDLE_TIMEOUT', 3600),
    'rate_limit_max' => 10,
    'rate_limit_window' => 300,
];
