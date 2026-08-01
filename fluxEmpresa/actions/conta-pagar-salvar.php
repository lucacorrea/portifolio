<?php

declare(strict_types=1);

require __DIR__ . '/financial-registration-action-common.php';

financial_registration_require_post();
$editing = trim((string) ($_POST['id'] ?? '')) !== '';
[$application, $session] = financial_registration_context($editing ? 'contas_pagar.editar' : 'contas_pagar.criar', 'contas-pagar.php');
$validationFailed = false;

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->accountsPayableManagement()->saveAccount(
        $editing ? financial_registration_positive_int($_POST['id'] ?? null) : null,
        $_POST,
        $user->id()
    );
    $session->flash('success', $editing
        ? 'Conta a pagar atualizada com sucesso.'
        : 'Conta cadastrada com o código ' . $result['code'] . '.');
} catch (InvalidArgumentException $exception) {
    $validationFailed = true;
    financial_registration_store_recovery('payable_form_recovery', $editing ? 'edit' : 'create', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Accounts payable save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar a conta a pagar.');
}

financial_registration_redirect($application, 'contas-pagar.php' . ($validationFailed ? '?modal=' . ($editing ? 'edit' : 'create') : ''));
