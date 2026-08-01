<?php

declare(strict_types=1);

require __DIR__ . '/financial-registration-action-common.php';

financial_registration_require_post();
[$application, $session] = financial_registration_context('contas_pagar.estornar_pagamento', 'contas-pagar.php');

try {
    $user = $application->authorization()->requireLogin();
    $application->accountsPayableManagement()->reverseInstallmentPayment(
        financial_registration_positive_int($_POST['parcela_id'] ?? null),
        (string) ($_POST['motivo'] ?? ''),
        $user->id()
    );
    $session->flash('success', 'Quitação estornada com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts payable installment reversal failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível estornar a quitação.');
}

financial_registration_redirect($application, 'contas-pagar.php');
