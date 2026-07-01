<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SEMAS_WHATSAPP_SESS');
    session_start();
}

function whatsapp_session_timeout_seconds(): int
{
    require_once __DIR__ . '/../config/env.php';
    semas_whatsapp_load_env();
    $security = require __DIR__ . '/../config/security.php';
    return (int)($security['session_timeout'] ?? 3600);
}

function whatsapp_session_touch(): void
{
    $_SESSION['semas_whatsapp_last_activity'] = time();
}

function whatsapp_session_expired(): bool
{
    $last = (int)($_SESSION['semas_whatsapp_last_activity'] ?? 0);
    return $last > 0 && (time() - $last) > whatsapp_session_timeout_seconds();
}
