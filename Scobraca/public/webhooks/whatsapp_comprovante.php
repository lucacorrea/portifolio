<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once APP_PATH . '/Services/WhatsAppService.php';

header('Content-Type: application/json; charset=utf-8');

function webhook_json(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    webhook_json(['ok' => false, 'message' => 'Método inválido.'], 405);
}

$expectedToken = whatsapp_expected_webhook_token();
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$headerToken = (string) ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ($_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? ''));
$providedToken = str_starts_with($authorization, 'Bearer ')
    ? substr($authorization, 7)
    : $headerToken;

if ($providedToken === '') {
    $providedToken = (string) ($_GET['token'] ?? '');
}

if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    webhook_json(['ok' => false, 'message' => 'Token do webhook inválido ou não configurado.'], 403);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);

if (!is_array($input)) {
    webhook_json(['ok' => false, 'message' => 'JSON inválido.'], 400);
}

try {
    $result = whatsapp_processar_comprovante_recebido($input);
    webhook_json($result, !empty($result['ok']) ? 200 : 422);
} catch (Throwable $e) {
    error_log('[WEBHOOK WHATSAPP COMPROVANTE] ' . $e->getMessage());
    webhook_json(['ok' => false, 'message' => 'Erro interno ao processar comprovante.'], 500);
}
