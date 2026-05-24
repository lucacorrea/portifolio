<?php
require_once __DIR__ . '/../includes/orders.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

$code = order_clean_text($_GET['pedido'] ?? $_GET['codigo'] ?? $_POST['pedido'] ?? $_POST['codigo'] ?? '', 40);
$pedido = order_find_by_code($code);

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'pedido' => [
        'id' => (int) $pedido['id'],
        'codigo' => $pedido['codigo'],
        'cliente' => $pedido['cliente_nome'],
        'status' => $pedido['status'],
        'status_label' => order_status_label((string) $pedido['status']),
        'status_pagamento' => $pedido['status_pagamento'],
        'status_pagamento_label' => order_payment_status_label((string) $pedido['status_pagamento']),
        'forma_pagamento' => order_payment_method_label((string) $pedido['forma_pagamento']),
        'recebimento' => order_receipt_label((string) $pedido['recebimento']),
        'subtotal' => (float) $pedido['subtotal'],
        'desconto' => (float) $pedido['desconto_total'],
        'taxa_entrega' => (float) $pedido['taxa_entrega'],
        'total' => (float) $pedido['total'],
        'criado_em' => $pedido['criado_em'],
    ],
    'itens' => order_items((int) $pedido['id']),
    'historico' => order_history((int) $pedido['id']),
    'pagamento' => order_payment((int) $pedido['id']),
], JSON_UNESCAPED_UNICODE);
