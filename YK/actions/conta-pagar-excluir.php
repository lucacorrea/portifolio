<?php

declare(strict_types=1);

require __DIR__ . '/financial-registration-action-common.php';

financial_registration_require_post();
[$application, $session] = financial_registration_context('contas_pagar.cancelar', 'contas-pagar.php');

try {
    $user = $application->authorization()->requireLogin();
    $application->accountsPayableManagement()->deleteAccount(
        financial_registration_positive_int($_POST['id'] ?? null),
        (string) ($_POST['motivo'] ?? ''),
        $user->id(),
        $application->authorization()->can('contas_pagar.estornar_pagamento')
    );
    $session->flash('success', 'Conta a pagar excluída com sucesso. O histórico financeiro foi preservado.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts payable delete failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível excluir a conta a pagar.');
}

financial_registration_redirect($application, 'contas-pagar.php');
