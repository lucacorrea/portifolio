<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('contas_receber.estornar_pagamento');

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->paymentManagement()->reverseAccountsReceivablePayment(
        os_posted_positive_int('pagamento_id'),
        (string) ($_POST['motivo'] ?? ''),
        $user->id()
    );

    $message = 'Pagamento excluído com estorno financeiro e saldo da conta recalculado.';
    if ($result['receipt_cancelled']) {
        $message .= ' O recibo vinculado também foi cancelado.';
    }
    $session->flash('success', $message);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts receivable payment reversal failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível excluir o pagamento.');
}

os_redirect_back($application, 'contas-receber.php');
