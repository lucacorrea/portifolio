<?php
declare(strict_types=1);

if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(403);
    exit('Acesso negado.');
}

$whatsappGuard = __DIR__ . '/auth/guard.php';
if (is_file($whatsappGuard)) {
    require_once $whatsappGuard;
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SEMAS_WHATSAPP_SESS');
    session_start();
}

require_once __DIR__ . '/config/env.php';
semas_whatsapp_load_env();

define('SEMAS_WHATSAPP_DIR', __DIR__);
define('SEMAS_WHATSAPP_LOG_DIR', __DIR__ . '/storage/logs');

function semas_whatsapp_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $whatsapp = require __DIR__ . '/config/whatsapp.php';
    $security = require __DIR__ . '/config/security.php';
    $config = array_merge($whatsapp, [
        'timeout' => (int)($whatsapp['timeout'] ?? 15),
        'message_limit' => (int)($whatsapp['message_limit'] ?? 3000),
        'log_dir' => SEMAS_WHATSAPP_LOG_DIR,
        'rate_limit_max' => (int)($security['rate_limit_max'] ?? 10),
        'rate_limit_window' => (int)($security['rate_limit_window'] ?? 300),
    ]);

    return $config;
}

function semas_whatsapp_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function semas_whatsapp_is_authenticated(): bool
{
    if (function_exists('whatsapp_auth_check')) {
        return whatsapp_auth_check();
    }

    return !empty($_SESSION['semas_whatsapp_user_id']) && !empty($_SESSION['semas_whatsapp_role']);
}

function semas_whatsapp_require_auth(bool $json = true): void
{
    if (semas_whatsapp_is_authenticated()) {
        return;
    }

    if ($json) {
        semas_whatsapp_json([
            'sucesso' => false,
            'mensagem' => 'Acesso não autorizado.',
        ], 401);
    }

    header('Location: login.php');
    exit;
}

function semas_whatsapp_csrf_token(): string
{
    if (empty($_SESSION['semas_whatsapp_csrf'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['semas_whatsapp_csrf'] = bin2hex(random_bytes(32));
        } else {
            $_SESSION['semas_whatsapp_csrf'] = sha1(uniqid('', true));
        }
    }

    return (string)$_SESSION['semas_whatsapp_csrf'];
}

function semas_whatsapp_validate_csrf(?string $token): bool
{
    $sessionToken = (string)($_SESSION['semas_whatsapp_csrf'] ?? '');
    return $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
}

function semas_whatsapp_check_rate_limit(): bool
{
    $now = time();
    $config = semas_whatsapp_config();
    $window = (int)($_SESSION['semas_whatsapp_rate_window'] ?? 0);
    $count = (int)($_SESSION['semas_whatsapp_rate_count'] ?? 0);

    if ($window <= 0 || ($now - $window) > (int)$config['rate_limit_window']) {
        $_SESSION['semas_whatsapp_rate_window'] = $now;
        $_SESSION['semas_whatsapp_rate_count'] = 1;
        return true;
    }

    if ($count >= (int)$config['rate_limit_max']) {
        return false;
    }

    $_SESSION['semas_whatsapp_rate_count'] = $count + 1;
    return true;
}

function semas_whatsapp_safe_context(array $context): array
{
    $blocked = ['token', 'senha', 'password', 'secret', 'key', 'api_key', 'authorization'];
    $safe = [];

    foreach ($context as $key => $value) {
        $lower = strtolower((string)$key);
        foreach ($blocked as $word) {
            if (strpos($lower, $word) !== false) {
                $safe[$key] = '[removido]';
                continue 2;
            }
        }

        if (is_string($value) && preg_match('/(bearer\s+|[a-f0-9]{32,}|eyJ[a-zA-Z0-9_-]+)/i', $value)) {
            $safe[$key] = '[removido]';
            continue;
        }

        $safe[$key] = $value;
    }

    return $safe;
}
