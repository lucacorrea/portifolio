<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/whatsapp.php';

require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ' . site_url('admin/pedidos.php?error=acao_invalida'));
    exit;
}

$orderId = filter_var($_POST['pedido_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

try {
    $result = whatsapp_send_order_message($orderId, true, 'pedido_reenvio');
    $status = (string) ($result['status'] ?? 'erro');
    if (in_array($status, ['enviado', 'simulado'], true)) {
        header('Location: ' . site_url('admin/pedidos.php?success=whatsapp_reenviado'));
    } else {
        header('Location: ' . site_url('admin/pedidos.php?error=whatsapp_nao_enviado'));
    }
} catch (Throwable $error) {
    error_log('[ArteFlor][order-whatsapp-resend] ' . $error->getMessage());
    header('Location: ' . site_url('admin/pedidos.php?error=whatsapp_nao_enviado'));
}
exit;
