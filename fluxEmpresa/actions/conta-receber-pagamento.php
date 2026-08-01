<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('contas_receber.registrar_pagamento');

try {
    $user = $application->authorization()->requireLogin();
    $application->paymentManagement()->registerAccountsReceivablePayment(
        os_posted_positive_int('id'),
        (string) ($_POST['valor'] ?? ''),
        (string) ($_POST['forma_pagamento'] ?? ''),
        isset($_POST['observacao']) ? (string) $_POST['observacao'] : null,
        $user->id()
    );
    $session->flash('success', 'Pagamento registrado e caixa atualizado.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts receivable payment failed: ' . $exception->getMessage());
    $session->flash('danger', 'Nao foi possivel registrar o pagamento.');
}

os_redirect_back($application, 'contas-receber.php');
