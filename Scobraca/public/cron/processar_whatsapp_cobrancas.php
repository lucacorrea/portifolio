<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once APP_PATH . '/Services/WhatsAppService.php';

header('Content-Type: application/json; charset=utf-8');

$expectedToken = trim((string) env('WHATSAPP_CRON_TOKEN', ''));
$providedToken = trim((string) ($_GET['token'] ?? ''));

if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token do cron inválido ou não configurado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    whatsapp_ensure_tables();
    $summary = whatsapp_processar_cobrancas_todas_empresas();

    echo json_encode(['ok' => true, 'summary' => $summary], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[CRON WHATSAPP COBRANCAS] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erro interno ao processar cobranças.'], JSON_UNESCAPED_UNICODE);
}
