<?php

declare(strict_types=1);

require __DIR__ . '/financial-registration-action-common.php';

financial_registration_require_post();
[$application, $session] = financial_registration_context('contas_pagar.cancelar', 'contas-pagar.php');

try {
    $user = $application->authorization()->requireLogin();
    $application->accountsPayableManagement()->cancelAccount(
        financial_registration_positive_int($_POST['id'] ?? null),
        (string) ($_POST['motivo'] ?? ''),
        $user->id()
    );
    $session->flash('success', 'Conta a pagar cancelada com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts payable cancel failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível cancelar a conta a pagar.');
}

financial_registration_redirect($application, 'contas-pagar.php');
