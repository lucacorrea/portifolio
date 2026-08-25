<?php

declare(strict_types=1);

use App\Catalog\DTO\ProductFormData;

require __DIR__
    . '/produto-action-common.php';

/**
 * Registra falhas de cadastro/edição de produto em arquivo próprio.
 * Não envia dados sensíveis para o navegador.
 */
function product_save_log(
    Throwable $exception
): void {
    $logDirectory =
        dirname(__DIR__)
        . '/storage/logs';

    if (!is_dir($logDirectory)) {
        @mkdir(
            $logDirectory,
            0755,
            true
        );
    }

    $logFile =
        $logDirectory
        . '/product-save.log';

    $message = sprintf(
        "[%s] %s: %s | %s:%d%s",
        date('Y-m-d H:i:s'),
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        PHP_EOL
    );

    @file_put_contents(
        $logFile,
        $message,
        FILE_APPEND | LOCK_EX
    );
}

product_require_post_request();

$rawProductId = trim(
    (string) (
        $_POST['id']
        ?? ''
    )
);

$isEditing =
    $rawProductId !== '';

$requiredPermission =
    $isEditing
        ? 'produto.editar'
        : 'produto.criar';

[$application, $session] =
    product_action_context(
        $requiredPermission
    );

try {
    $authorization =
        $application
            ->authorization();

    $canCost =
        $authorization->can(
            'produto.visualizar_preco_custo'
        );

    $canSale =
        $authorization->can(
            'produto.visualizar_preco_venda'
        );

    $productId =
        $isEditing
            ? product_posted_positive_int(
                'id'
            )
            : null;

    $service =
        $application
            ->productManagement();

    $existing =
        $productId !== null
            ? $service->getProduct(
                $productId
            )
            : null;

    $data =
        ProductFormData::fromArray([
            'name' =>
                $_POST['name']
                ?? '',

            'description' =>
                $_POST['description']
                ?? '',

            'category' =>
                $_POST['category']
                ?? '',

            'manufacturer' =>
                $_POST['manufacturer']
                ?? '',

            'unit' =>
                $_POST['unit']
                ?? 'UN',

            /*
             * Dados fiscais
             */
            'ncm' =>
                $_POST['ncm']
                ?? '',

            'cest' =>
                $_POST['cest']
                ?? '',

            'origin' =>
                $_POST['origin']
                ?? '',

            'default_cfop' =>
                $_POST['default_cfop']
                ?? '',

            'icms_cst' =>
                $_POST['icms_cst']
                ?? '',

            'csosn' =>
                $_POST['csosn']
                ?? '',

            'pis_cst' =>
                $_POST['pis_cst']
                ?? '',

            'cofins_cst' =>
                $_POST['cofins_cst']
                ?? '',

            'icms_rate' =>
                $_POST['icms_rate']
                ?? '',

            'pis_rate' =>
                $_POST['pis_rate']
                ?? '',

            'cofins_rate' =>
                $_POST['cofins_rate']
                ?? '',

            'tax_gtin' =>
                $_POST['tax_gtin']
                ?? '',

            'tax_unit' =>
                $_POST['tax_unit']
                ?? '',

            'ibs_cbs_cst' =>
                $_POST['ibs_cbs_cst']
                ?? '',

            'ibs_cbs_classification' =>
                $_POST[
                    'ibs_cbs_classification'
                ]
                ?? '',

            /*
             * Dados comerciais
             */
            'barcode' =>
                $_POST['barcode']
                ?? '',

            'cost_price' =>
                $canCost
                    ? (
                        $_POST['cost_price']
                        ?? '0'
                    )
                    : (
                        $existing !== null
                            ? $existing->costPrice()
                            : '0'
                    ),

            'sale_price' =>
                $canSale
                    ? (
                        $_POST['sale_price']
                        ?? '0'
                    )
                    : (
                        $existing !== null
                            ? $existing->salePrice()
                            : '0'
                    ),

            'stock' =>
                $_POST['stock']
                ?? '0',

            'minimum_stock' =>
                $_POST['minimum_stock']
                ?? '0',

            'location' =>
                $_POST['location']
                ?? '',

            'status' =>
                $_POST['status']
                ?? 'ativo',
        ]);

    /*
     * EDIÇÃO
     */
    if ($existing !== null) {
        $data =
            $data->withPrices(
                $canCost
                    ? $data->costPrice()
                    : $existing->costPrice(),

                $canSale
                    ? $data->salePrice()
                    : $existing->salePrice()
            );

        $service->updateProduct(
            $productId,
            $data
        );

        $session->flash(
            'success',
            'Produto atualizado com sucesso.'
        );

        product_redirect(
            $application,
            'produtos.php'
        );
    }

    /*
     * NOVO PRODUTO
     */
    $product =
        $service->createProduct(
            $data
        );

    $session->flash(
        'success',
        'Produto cadastrado com o código '
        . $product->displayCode()
        . '.'
    );

    product_redirect(
        $application,
        'produtos.php'
    );
} catch (InvalidArgumentException $exception) {
    product_save_log(
        $exception
    );

    /*
     * Recalcula permissões somente para recuperação
     * dos campos protegidos.
     */
    $authorization =
        $application
            ->authorization();

    $canCost =
        $authorization->can(
            'produto.visualizar_preco_custo'
        );

    $canSale =
        $authorization->can(
            'produto.visualizar_preco_venda'
        );

    $recovery = [
        'id' =>
            $rawProductId,

        'name' =>
            $_POST['name']
            ?? '',

        'description' =>
            $_POST['description']
            ?? '',

        'category' =>
            $_POST['category']
            ?? '',

        'manufacturer' =>
            $_POST['manufacturer']
            ?? '',

        'unit' =>
            $_POST['unit']
            ?? 'UN',

        'ncm' =>
            $_POST['ncm']
            ?? '',

        'cest' =>
            $_POST['cest']
            ?? '',

        'origin' =>
            $_POST['origin']
            ?? '',

        'default_cfop' =>
            $_POST['default_cfop']
            ?? '',

        'icms_cst' =>
            $_POST['icms_cst']
            ?? '',

        'csosn' =>
            $_POST['csosn']
            ?? '',

        'pis_cst' =>
            $_POST['pis_cst']
            ?? '',

        'cofins_cst' =>
            $_POST['cofins_cst']
            ?? '',

        'icms_rate' =>
            $_POST['icms_rate']
            ?? '',

        'pis_rate' =>
            $_POST['pis_rate']
            ?? '',

        'cofins_rate' =>
            $_POST['cofins_rate']
            ?? '',

        'tax_gtin' =>
            $_POST['tax_gtin']
            ?? '',

        'tax_unit' =>
            $_POST['tax_unit']
            ?? '',

        'ibs_cbs_cst' =>
            $_POST['ibs_cbs_cst']
            ?? '',

        'ibs_cbs_classification' =>
            $_POST[
                'ibs_cbs_classification'
            ]
            ?? '',

        'barcode' =>
            $_POST['barcode']
            ?? '',

        'stock' =>
            $_POST['stock']
            ?? '0',

        'minimum_stock' =>
            $_POST['minimum_stock']
            ?? '0',

        'location' =>
            $_POST['location']
            ?? '',

        'status' =>
            $_POST['status']
            ?? 'ativo',
    ];

    if ($canCost) {
        $recovery['cost_price'] =
            $_POST['cost_price']
            ?? '0';
    }

    if ($canSale) {
        $recovery['sale_price'] =
            $_POST['sale_price']
            ?? '0';
    }

    product_store_form_recovery(
        $isEditing
            ? 'edit'
            : 'create',

        $recovery,
        $exception->getMessage()
    );

    $session->flash(
        'danger',
        $exception->getMessage()
    );

    product_redirect(
        $application,
        'produtos.php?modal='
        . (
            $isEditing
                ? 'edit'
                : 'create'
        )
    );
} catch (Throwable $exception) {
    product_save_log(
        $exception
    );

    $session->flash(
        'danger',
        'Não foi possível salvar o produto. '
        . 'O erro técnico foi registrado.'
    );

    product_redirect(
        $application,
        'produtos.php'
    );
}