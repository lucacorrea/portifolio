<?php
require_once __DIR__ . '/../../includes/products.php';

$adminUser = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . site_url('admin/produtos.php?error=metodo_invalido'));
    exit;
}

if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ' . site_url('admin/produtos.php?error=csrf'));
    exit;
}

$action = (string) ($_POST['action'] ?? '');

try {
    product_update_status((int) ($_POST['product_id'] ?? 0), $action);
    $success = $action === 'inativar' ? 'produto_inativado' : 'produto_ativado';
    header('Location: ' . site_url('admin/produtos.php?success=' . $success));
    exit;
} catch (InvalidArgumentException $exception) {
    error_log('[ArteFlor][product-status-validation] ' . $exception->getMessage());
    header('Location: ' . site_url('admin/produtos.php?error=acao_invalida'));
    exit;
} catch (Throwable $exception) {
    error_log('[ArteFlor][product-status] ' . $exception->getMessage());
    header('Location: ' . site_url('admin/produtos.php?error=acao_invalida'));
    exit;
}
