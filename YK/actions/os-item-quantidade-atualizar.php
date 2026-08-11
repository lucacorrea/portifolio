<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();

header(
    'Content-Type: application/json; charset=utf-8'
);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    [$application] = os_action_context(
        'os.finalizar',
        false
    );

    $application->csrf()->requireValid(
        isset($_POST['csrf_token'])
            ? (string) $_POST['csrf_token']
            : null
    );

    $orderId = os_positive_int(
        $_POST['order_id'] ?? null
    );

    $itemId = os_positive_int(
        $_POST['item_id'] ?? null
    );

    $quantity = trim(
        (string) ($_POST['quantity'] ?? '')
    );

    $result = $application
        ->serviceOrderManagement()
        ->updateItemQuantityForFinalization(
            $orderId,
            $itemId,
            $quantity
        );

    echo json_encode(
        [
            'success' => true,
            'message' => 'Quantidade atualizada com sucesso.',
            'data' => $result,
        ],
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode(
        [
            'success' => false,
            'error' => $exception->getMessage(),
        ],
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
    );
} catch (RuntimeException $exception) {
    error_log(
        'OS quantity update access failed: '
        . $exception->getMessage()
    );

    http_response_code(403);

    echo json_encode(
        [
            'success' => false,
            'error' => 'Não foi possível validar a operação.',
        ],
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    error_log(
        'OS item quantity update failed: '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'error' => 'Não foi possível atualizar a quantidade.',
        ],
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
    );
}