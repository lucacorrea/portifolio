<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
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

        'items' => array_map(
            static fn ($item): array => [
                'id' => $item->id(),
                'type' => $item->type(),
                'reference_id' => $item->referenceId(),
                'description' => $item->description(),
                'unit' => $item->unit(),
                'quantity' => $item->quantity(),
                'unit_price' => $item->unitPrice(),
                'discount' => $item->discount(),
                'subtotal' => $item->subtotal(),
            ],
            $items
        ),
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