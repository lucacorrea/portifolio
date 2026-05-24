<?php
require_once __DIR__ . '/../../includes/pdv.php';

$adminUser = require_admin();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados da venda inválidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!admin_csrf_is_valid($payload['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $order = pdv_create_sale_from_payload($payload, $adminUser);
    echo json_encode([
        'success' => true,
        'codigo' => $order['codigo'],
        'pedido_id' => (int) $order['id'],
        'total' => (float) $order['total'],
        'message' => 'Venda finalizada no banco.',
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[ArteFlor][pdv-finalizar] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível finalizar a venda. Tente novamente.'], JSON_UNESCAPED_UNICODE);
}
