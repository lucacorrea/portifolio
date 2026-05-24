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

try {
    $newProductId = product_duplicate((int) ($_POST['product_id'] ?? 0));
    header('Location: ' . site_url('admin/produto-form.php?id=' . $newProductId . '&success=produto_duplicado'));
    exit;
} catch (InvalidArgumentException $exception) {
    error_log('[ArteFlor][product-duplicate-validation] ' . $exception->getMessage());
    header('Location: ' . site_url('admin/produtos.php?error=produto_nao_encontrado'));
    exit;
} catch (Throwable $exception) {
    error_log('[ArteFlor][product-duplicate] ' . $exception->getMessage());
    header('Location: ' . site_url('admin/produtos.php?error=acao_invalida'));
    exit;
}
