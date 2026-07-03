<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['GET', 'POST']);
wpe_require_permission('visualizar');

$checks = [
    'php' => PHP_VERSION,
    'database' => isset($pdo) && $pdo instanceof PDO,
    'storage_logs_writable' => is_writable(__DIR__ . '/../storage/logs'),
    'bridge_logs_writable' => is_writable(__DIR__ . '/../bridge/logs'),
    'bridge_sessions_writable' => is_writable(__DIR__ . '/../bridge/storage/sessions'),
];

try {
    $service = new WhatsappService();
    $bridge = $service->executarRequisicao('/health', [], 'GET');
    $checks['bridge'] = $bridge['sucesso'];
    $checks['bridge_status'] = $bridge['dados']['status'] ?? null;
} catch (Throwable $e) {
    $checks['bridge'] = false;
}

$ok = !in_array(false, $checks, true);
wpe_json($ok, $ok ? 'Health check ok.' : 'Health check com falhas.', $checks, [], $ok ? 200 : 503);
