<?php
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/whatsapp.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados do pedido inválidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pedido = order_create_from_checkout($payload);
    $whatsapp = ['enabled' => false, 'status' => 'desativado'];

    try {
        $whatsapp = whatsapp_send_order_message((int) $pedido['id']);
    } catch (Throwable $error) {
        error_log('[ArteFlor][checkout-whatsapp] ' . $error->getMessage());
        $whatsapp = ['enabled' => true, 'status' => 'erro'];
    }

    echo json_encode([
        'success' => true,
        'codigo' => $pedido['codigo'],
        'pedido_id' => (int) $pedido['id'],
        'status' => $pedido['status'],
        'mensagem' => 'Pedido criado com sucesso.',
        'whatsapp' => [
            'enabled' => (bool) ($whatsapp['enabled'] ?? false),
            'status' => (string) ($whatsapp['status'] ?? 'desativado'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('[ArteFlor][checkout-order] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível criar o pedido agora. Tente novamente em instantes.'], JSON_UNESCAPED_UNICODE);
}
