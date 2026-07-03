<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function whatsapp_csrf_token(): string
{
    if (empty($_SESSION['semas_whatsapp_csrf'])) {
        $_SESSION['semas_whatsapp_csrf'] = function_exists('random_bytes') ? bin2hex(random_bytes(32)) : sha1(uniqid('', true));
    }

    return (string)$_SESSION['semas_whatsapp_csrf'];
}

function whatsapp_csrf_validate(string $token): bool
{
    $sessionToken = (string)($_SESSION['semas_whatsapp_csrf'] ?? '');
    return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
}
