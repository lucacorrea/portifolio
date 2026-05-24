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
    product_update_stock(
        (int) ($_POST['product_id'] ?? 0),
        (string) ($_POST['tipo'] ?? ''),
        (int) ($_POST['quantidade'] ?? -1),
        trim((string) ($_POST['motivo'] ?? ''))
    );
    header('Location: ' . site_url('admin/produtos.php?success=estoque_atualizado'));
    exit;
} catch (InvalidArgumentException $exception) {
    error_log('[ArteFlor][product-stock-validation] ' . $exception->getMessage());
    $message = $exception->getMessage();
    $error = str_contains($message, 'negativo') ? 'estoque_negativo' : (str_contains($message, 'alterar') ? 'estoque_sem_alteracao' : 'acao_invalida');
    header('Location: ' . site_url('admin/produtos.php?error=' . $error));
    exit;
} catch (Throwable $exception) {
    error_log('[ArteFlor][product-stock] ' . $exception->getMessage());
    header('Location: ' . site_url('admin/produtos.php?error=acao_invalida'));
    exit;
}
