<?php

declare(strict_types=1);

require __DIR__ . '/financial-registration-action-common.php';

financial_registration_require_post();
[$application, $session] = financial_registration_context('contas_pagar.quitar', 'contas-pagar.php');

try {
    $user = $application->authorization()->requireLogin();
    $application->accountsPayableManagement()->settleInstallment(
        financial_registration_positive_int($_POST['parcela_id'] ?? null),
        (string) ($_POST['forma_pagamento'] ?? ''),
        $user->id()
    );
    $session->flash('success', 'Parcela quitada com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts payable installment settlement failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível quitar a parcela.');
}

financial_registration_redirect($application, 'contas-pagar.php');
