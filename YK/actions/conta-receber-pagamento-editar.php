<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('contas_receber.estornar_pagamento');

try {
    $authorization = $application->authorization();
    $authorization->requirePermission('contas_receber.registrar_pagamento');
    $user = $authorization->requireLogin();

    $result = $application->paymentManagement()->editAccountsReceivablePayment(
        os_posted_positive_int('pagamento_id'),
        (string) ($_POST['valor'] ?? ''),
        (string) ($_POST['forma_pagamento'] ?? ''),
        (string) ($_POST['data_pagamento'] ?? ''),
        isset($_POST['observacao']) ? (string) $_POST['observacao'] : null,
        $user->id()
    );

    if ($result['financial_correction']) {
        $message = 'Pagamento corrigido e Caixa atualizado.';
        if ($result['receipt_cancelled']) {
            $message .= ' O recibo anterior foi cancelado; gere um novo recibo para o pagamento corrigido.';
        }
        $session->flash('success', $message);
    } else {
        $session->flash('success', 'Pagamento atualizado.');
    }
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts receivable payment edit failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível editar o pagamento.');
}

os_redirect_back($application, 'contas-receber.php');
