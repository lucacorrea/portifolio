<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/whatsapp.php';

$adminUser = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ' . site_url('admin/pedidos.php?error=acao_invalida'));
    exit;
}

$orderId = filter_var($_POST['pedido_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$status = order_clean_text($_POST['novo_status'] ?? '', 60);
$note = order_clean_text($_POST['observacao'] ?? '', 255);

try {
    order_update_status($orderId, $status, (int) ($adminUser['id'] ?? 0), $note);
    $config = whatsapp_config();
    if (!empty($config['whatsapp_send_on_status_change'])) {
        try {
            whatsapp_send_order_message($orderId, true, 'pedido_status');
        } catch (Throwable $error) {
            error_log('[ArteFlor][order-status-whatsapp] ' . $error->getMessage());
        }
    }

    header('Location: ' . site_url('admin/pedidos.php?success=status_atualizado'));
} catch (InvalidArgumentException $error) {
    header('Location: ' . site_url('admin/pedidos.php?error=pedido_invalido'));
} catch (Throwable $error) {
    error_log('[ArteFlor][order-status] ' . $error->getMessage());
    header('Location: ' . site_url('admin/pedidos.php?error=falha_status'));
}
exit;
