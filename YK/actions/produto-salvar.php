<?php

declare(strict_types=1);

use App\Catalog\DTO\ProductFormData;

require __DIR__ . '/produto-action-common.php';

product_require_post_request();

$rawProductId = trim((string) ($_POST['id'] ?? ''));
$isEditing = $rawProductId !== '';
$requiredPermission = $isEditing ? 'produto.editar' : 'produto.criar';

[$application, $session] = product_action_context($requiredPermission);

$authorization = $application->authorization();
$canCost = $authorization->can('produto.visualizar_preco_custo');
$canSale = $authorization->can('produto.visualizar_preco_venda');

try {
    $productId = $isEditing ? product_posted_positive_int('id') : null;
    $service = $application->productManagement();
    $existing = $productId !== null ? $service->getProduct($productId) : null;

    $data = ProductFormData::fromArray([
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
        'category' => $_POST['category'] ?? '',
        'manufacturer' => $_POST['manufacturer'] ?? '',
        'unit' => $_POST['unit'] ?? 'un',
        'ncm' => $_POST['ncm'] ?? '',
        'barcode' => $_POST['barcode'] ?? '',
        'cost_price' => $canCost ? ($_POST['cost_price'] ?? '0') : '0',
        'sale_price' => $canSale ? ($_POST['sale_price'] ?? '0') : '0',
        'stock' => $_POST['stock'] ?? '0',
        'minimum_stock' => $_POST['minimum_stock'] ?? '0',
        'location' => $_POST['location'] ?? '',
        'status' => $_POST['status'] ?? 'ativo',
    ]);

    if ($existing !== null) {
        $data = $data->withPrices(
            $canCost ? $data->costPrice() : $existing->costPrice(),
            $canSale ? $data->salePrice() : $existing->salePrice()
        );
        $service->updateProduct($productId, $data);
        $session->flash('success', 'Produto atualizado com sucesso.');
    } else {
        $product = $service->createProduct($data);
        $session->flash('success', 'Produto cadastrado com o código ' . $product->displayCode() . '.');
    }
} catch (InvalidArgumentException $exception) {
    $recovery = [
        'id' => $rawProductId,
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
        'category' => $_POST['category'] ?? '',
        'manufacturer' => $_POST['manufacturer'] ?? '',
        'unit' => $_POST['unit'] ?? 'un',
        'ncm' => $_POST['ncm'] ?? '',
        'barcode' => $_POST['barcode'] ?? '',
        'stock' => $_POST['stock'] ?? '0',
        'minimum_stock' => $_POST['minimum_stock'] ?? '0',
        'location' => $_POST['location'] ?? '',
        'status' => $_POST['status'] ?? 'ativo',
    ];

    if ($canCost) {
        $recovery['cost_price'] = $_POST['cost_price'] ?? '0';
    }

    if ($canSale) {
        $recovery['sale_price'] = $_POST['sale_price'] ?? '0';
    }

    product_store_form_recovery($isEditing ? 'edit' : 'create', $recovery, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    product_redirect($application, 'produtos.php?modal=' . ($isEditing ? 'edit' : 'create'));
} catch (Throwable $exception) {
    error_log('Product save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar o produto.');
}

product_redirect($application, 'produtos.php');
