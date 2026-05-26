<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/whatsapp.php';

require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$action = order_clean_text($_GET['action'] ?? '', 30);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function whatsapp_bridge_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $config = whatsapp_config();

    if ($action === 'status') {
        if ($method !== 'GET') {
            whatsapp_bridge_json(['success' => false, 'message' => 'Método inválido.'], 405);
        }

        $status = whatsapp_bridge_status($config);
        $status['updated_at'] = date('H:i:s');
        whatsapp_bridge_json($status);
    }

    if ($action === 'qrcode') {
        if ($method !== 'GET') {
            whatsapp_bridge_json(['success' => false, 'message' => 'Método inválido.'], 405);
        }

        $qr = whatsapp_bridge_qrcode($config);
        $qr['updated_at'] = date('H:i:s');
        whatsapp_bridge_json($qr);
    }

    if ($action === 'logout') {
        if ($method !== 'POST') {
            whatsapp_bridge_json(['success' => false, 'message' => 'Método inválido.'], 405);
        }

        if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
            whatsapp_bridge_json(['success' => false, 'message' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        whatsapp_bridge_json(whatsapp_bridge_logout($config));
    }

    whatsapp_bridge_json(['success' => false, 'message' => 'Ação inválida.'], 400);
} catch (Throwable $error) {
    error_log('[ArteFlor][whatsapp-bridge-action] ' . $error->getMessage());
    whatsapp_bridge_json([
        'success' => false,
        'connected' => false,
        'status' => 'erro',
        'message' => 'Não foi possível consultar a conexão WhatsApp agora.',
        'qr' => null,
        'number' => '',
    ], 500);
}
