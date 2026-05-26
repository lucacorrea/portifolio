<?php
require_once __DIR__ . '/../../includes/products.php';

$adminUser = require_admin();
$productId = (int) ($_POST['product_id'] ?? 0);
$redirect = $productId > 0
    ? site_url('admin/produto-form.php?id=' . $productId)
    : site_url('admin/produtos.php');
$withMessage = static fn(string $query): string => $redirect . (str_contains($redirect, '?') ? '&' : '?') . $query;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $withMessage('error=metodo_invalido'));
    exit;
}

if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $withMessage('error=csrf'));
    exit;
}

try {
    product_set_primary_image($productId, (int) ($_POST['image_id'] ?? 0));
    header('Location: ' . site_url('admin/produto-form.php?id=' . $productId . '&success=imagem_principal'));
    exit;
} catch (InvalidArgumentException $exception) {
    error_log('[ArteFlor][product-image-primary-validation] ' . $exception->getMessage());
    header('Location: ' . $withMessage('error=imagem_nao_encontrada'));
    exit;
} catch (Throwable $exception) {
    error_log('[ArteFlor][product-image-primary] ' . $exception->getMessage());
    header('Location: ' . $withMessage('error=acao_invalida'));
    exit;
}
