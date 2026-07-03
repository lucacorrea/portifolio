<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/guard.php';
if (!whatsapp_auth_check()) {
    wpe_json(false, 'Sessao expirada ou acesso nao autorizado.', [], [], 401);
}
whatsapp_session_touch();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmpregoCentralService.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    wpe_json(false, 'Conexao com banco indisponivel.', [], [], 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['semas_whatsapp_csrf'])) {
    $_SESSION['semas_whatsapp_csrf'] = function_exists('random_bytes') ? bin2hex(random_bytes(32)) : sha1(uniqid('', true));
}

function wpe_json(bool $success, string $message, array $data = [], array $errors = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'sucesso' => $success,
        'mensagem' => $message,
        'dados' => $data,
        'erros' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function wpe_input(): array
{
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode((string)$raw, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            wpe_json(false, 'JSON invalido.', [], [json_last_error_msg()], 400);
        }
        return $data;
    }

    return $_POST ?: $_GET;
}

function wpe_require_method(array $methods): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, $methods, true)) {
        wpe_json(false, 'Metodo nao permitido.', [], [], 405);
    }
}

function wpe_require_csrf(array $input): void
{
    $token = (string)($input['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['semas_whatsapp_csrf'] ?? '');
    if ($sessionToken === '' || $token === '' || !hash_equals($sessionToken, $token)) {
        wpe_json(false, 'Token CSRF invalido.', [], [], 403);
    }
}

function wpe_user_can(string $permission): bool
{
    return whatsapp_user_can($permission);
}

function wpe_require_permission(string $permission): void
{
    if (!wpe_user_can($permission)) {
        wpe_json(false, 'Perfil sem permissao para esta operacao.', [], [], 403);
    }
}

function wpe_service(PDO $pdo): EmpregoCentralService
{
    $service = new EmpregoCentralService($pdo);
    $service->ensureSchema();
    return $service;
}
