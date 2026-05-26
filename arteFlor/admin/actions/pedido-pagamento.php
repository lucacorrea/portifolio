<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orders.php';

$adminUser = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ' . site_url('admin/pedidos.php?error=acao_invalida'));
    exit;
}

$orderId = filter_var($_POST['pedido_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$action = order_clean_text($_POST['acao'] ?? '', 40);

try {
    order_update_payment($orderId, $action, (int) ($adminUser['id'] ?? 0));
    $message = $action === 'confirmar_pagamento' ? 'pagamento_confirmado' : 'pagamento_cancelado';
    header('Location: ' . site_url('admin/pedidos.php?success=' . $message));
} catch (InvalidArgumentException $error) {
    header('Location: ' . site_url('admin/pedidos.php?error=pedido_invalido'));
} catch (Throwable $error) {
    error_log('[ArteFlor][order-payment] ' . $error->getMessage());
    header('Location: ' . site_url('admin/pedidos.php?error=falha_pagamento'));
}
exit;
