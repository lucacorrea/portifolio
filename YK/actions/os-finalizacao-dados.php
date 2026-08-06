<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);

header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

try {
    [$application] = os_action_context(
        'os.finalizar',
        false
    );

    $orderId = os_positive_int(
        $_GET['id'] ?? null
    );

    $order = $application
        ->serviceOrderManagement()
        ->getOrder($orderId);

    if (!in_array(
        $order->status(),
        [
            'agendada',
            'em_execucao',
            'aguardando_peca',
        ],
        true
    )) {
        throw new InvalidArgumentException(
            'O status atual da OS não permite finalização.'
        );
    }

    $items = $application
        ->serviceOrderManagement()
        ->getOrderItems($orderId);

    if ($items === []) {
        throw new InvalidArgumentException(
            'A OS não possui itens cadastrados.'
        );
    }

    /*
     * Carrega o estoque atual de todos os produtos
     * utilizados na OS em uma única consulta.
     */
    $productIds = [];

    foreach ($items as $item) {
        if (
            $item->type() === 'produto'
            && $item->referenceId() !== null
        ) {
            $productIds[] = $item->referenceId();
        }
    }

    $productIds = array_values(
        array_unique($productIds)
    );

    $stockByProductId = [];

    if ($productIds !== []) {
        $placeholders = [];
        $parameters = [];

        foreach ($productIds as $index => $productId) {
            $parameterName = 'product_' . $index;

            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $productId;
        }

        $stockStatement = $application
            ->database()
            ->connection()
            ->prepare(
                'SELECT
                    id,
                    estoque,
                    unidade,
                    nome
                 FROM produtos
                 WHERE id IN (
                    ' . implode(', ', $placeholders) . '
                 )
                   AND excluido_em IS NULL'
            );

        $stockStatement->execute($parameters);

        foreach ($stockStatement->fetchAll() as $product) {
            $stockByProductId[
                (int) $product['id']
            ] = [
                'stock' => (string) (
                    $product['estoque'] ?? '0.000'
                ),
                'unit' => (string) (
                    $product['unidade'] ?? 'un'
                ),
                'name' => (string) (
                    $product['nome'] ?? ''
                ),
            ];
        }
    }

    $payloadItems = [];

    foreach ($items as $item) {
        $referenceId = $item->referenceId();
        $isProduct = $item->type() === 'produto';

        $productStock = (
            $isProduct
            && $referenceId !== null
        )
            ? (
                $stockByProductId[$referenceId]
                ?? null
            )
            : null;

        $requiredQuantity = (float) (
            $item->quantity()
        );

        $availableStock = $productStock === null
            ? null
            : (float) $productStock['stock'];

        $stockMissing = $isProduct
            && $productStock === null;

        $stockInsufficient = $isProduct
            && (
                $stockMissing
                || $availableStock === null
                || $requiredQuantity
                    > ($availableStock + 0.000001)
            );

        $payloadItems[] = [
            'id' => $item->id(),
            'type' => $item->type(),
            'reference_id' => $referenceId,
            'description' => $item->description(),
            'unit' => $item->unit(),
            'quantity' => $item->quantity(),
            'unit_price' => $item->unitPrice(),
            'discount' => $item->discount(),
            'subtotal' => $item->subtotal(),

            'stock_available' => $availableStock === null
                ? null
                : number_format(
                    $availableStock,
                    3,
                    '.',
                    ''
                ),

            'stock_missing' => $stockMissing,
            'stock_insufficient' => $stockInsufficient,
            'quantity_editable' => $isProduct,
        ];
    }

    $payload = [
        'order' => [
            'id' => $order->id(),
            'number' => $order->displayNumber(),
            'status' => $order->status(),

            'services_subtotal' => $order
                ->servicesSubtotal(),

            'products_subtotal' => $order
                ->productsSubtotal(),

            'others_subtotal' => $order
                ->othersSubtotal(),

            'discount' => $order->discount(),
            'increase' => $order->increase(),
            'total' => $order->total(),
        ],

        'items' => $payloadItems,
    ];

    echo json_encode(
        $payload,
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode(
        [
            'error' => $exception->getMessage(),
        ],
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    error_log(
        'OS finalization data load failed: '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'error' => 'Não foi possível carregar os dados da finalização.',
        ],
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
    );
}