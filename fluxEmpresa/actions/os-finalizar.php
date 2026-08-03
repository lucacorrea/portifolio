<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('os.finalizar');

try {
    $user = $application->authorization()->requireLogin();
    $orderId = os_posted_positive_int('id');
    $order = $application->serviceOrderManagement()->getOrder($orderId);
    $items = $application->serviceOrderManagement()->getOrderItems($orderId);
    if ($items === []) {
        throw new InvalidArgumentException('A OS precisa ter ao menos um item para ser finalizada.');
    }

    $executionItems = array_map(static fn($item): array => [
        'type' => $item->type(),
        'ordem_servico_item_id' => $item->id(),
        'reference_id' => $item->referenceId(),
        'description' => $item->description(),
        'unit' => $item->unit(),
        'quantity' => $item->quantity(),
        'unit_price' => $item->unitPrice(),
        'discount' => $item->discount(),
    ], $items);

    $result = $application->serviceOrderFinalization()->finalize(
        $orderId,
        [
            'execution_items' => $executionItems,
            'desconto' => $order->discount(),
            'acrescimo' => $order->increase(),
        ],
        $user->id()
    );
    if ($application->authorization()->can('contas_receber.registrar_pagamento')
        && $application->authorization()->can('recibo.emitir')) {
        os_store_post_completion_payment_prompt(
            $result['order_id'],
            $result['order_number'],
            $result['balance']
        );
    }
    $session->flash('success', 'OS finalizada. Confirme agora a situação do pagamento.');
    os_redirect_back($application, 'ordens-servico.php', ['modal' => null]);
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('finalize', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    os_redirect_back($application, 'ordens-servico.php', ['modal' => 'finalize']);
} catch (Throwable $exception) {
    error_log('OS finalization failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível finalizar a OS.');
}

os_redirect_back($application);
