<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('contas_receber.registrar_pagamento');
if (!$application->authorization()->can('recibo.emitir')) {
    $session->flash('danger', 'Você não possui permissão para emitir o recibo deste pagamento.');
    os_redirect_back($application, 'ordens-servico.php');
}

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->paymentManagement()->registerFinalizedOrderPayment(
        os_posted_positive_int('id'),
        (string) ($_POST['valor'] ?? ''),
        (string) ($_POST['forma_pagamento'] ?? ''),
        isset($_POST['observacao']) ? (string) $_POST['observacao'] : null,
        (string) ($_POST['payment_token'] ?? ''),
        $user->id()
    );
    $_SESSION['receipt_initial_print_grant'] = [
        'receipt_id' => $result['receipt_id'],
        'user_id' => $user->id(),
        'expires_at' => time() + 120,
    ];
    $session->flash(
        'success',
        $result['account_status'] === 'paga'
            ? 'OS paga, Caixa atualizado e recibo emitido.'
            : 'Pagamento parcial registrado, Caixa atualizado e recibo emitido.'
    );
    os_redirect($application, 'recibo-imprimir.php?id=' . $result['receipt_id']);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Finalized service order payment failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível pagar a OS. Nenhum lançamento parcial foi mantido.');
}

os_redirect_back($application, 'ordens-servico.php');
