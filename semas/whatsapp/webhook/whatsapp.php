<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmpregoCentralService.php';

function wh_json(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['sucesso' => $success, 'mensagem' => $message, 'dados' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wh_json(false, 'Metodo nao permitido.', [], 405);
}

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') === false) {
    wh_json(false, 'Content-Type invalido.', [], 415);
}

$appConfig = require __DIR__ . '/../config/app.php';
$whatsappConfig = require __DIR__ . '/../config/whatsapp.php';
$secret = (string)($whatsappConfig['webhook_secret'] ?? '');
if (is_string($secret) && trim($secret) !== '') {
    $header = (string)($_SERVER['HTTP_X_SEMAS_WEBHOOK_SECRET'] ?? '');
    if (!hash_equals(trim($secret), $header)) {
        wh_json(false, 'Webhook nao autorizado.', [], 401);
    }
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 1024 * 1024) {
    wh_json(false, 'Payload invalido.', [], 413);
}

$payload = json_decode($raw, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    wh_json(false, 'JSON invalido.', [], 400);
}

$instanceId = (string)($payload['instanceId'] ?? $payload['instance_id'] ?? '');
if ($instanceId !== (string)$appConfig['instance_id']) {
    wh_json(true, 'Evento ignorado por instancia diferente.', ['ignorado' => true], 202);
}

$event = (string)($payload['event'] ?? '');
if ($event !== '' && $event !== 'message.received') {
    wh_json(true, 'Evento ignorado.', ['event' => $event], 202);
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        wh_json(false, 'Conexao indisponivel.', [], 503);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $service = new EmpregoCentralService($pdo);
    $service->ensureSchema();
    wh_json(true, 'Mensagem registrada.', $service->registrarEntrada($payload));
} catch (RuntimeException $e) {
    wh_json(false, $e->getMessage(), [], 422);
} catch (Throwable $e) {
    wh_json(false, 'Falha ao processar webhook.', [], 500);
}
