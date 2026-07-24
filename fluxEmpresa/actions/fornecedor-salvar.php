<?php

declare(strict_types=1);

require __DIR__ . '/financial-registration-action-common.php';

financial_registration_require_post();
$editing = trim((string) ($_POST['id'] ?? '')) !== '';
[$application, $session] = financial_registration_context($editing ? 'fornecedor.editar' : 'fornecedor.criar', 'fornecedores.php');
$validationFailed = false;

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->supplierManagement()->saveSupplier(
        $editing ? financial_registration_positive_int($_POST['id'] ?? null) : null,
        $_POST,
        $user->id()
    );
    $session->flash('success', $editing
        ? 'Fornecedor atualizado com sucesso.'
        : 'Fornecedor cadastrado com o código ' . $result['code'] . '.');
} catch (InvalidArgumentException $exception) {
    $validationFailed = true;
    financial_registration_store_recovery('supplier_form_recovery', $editing ? 'edit' : 'create', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Supplier save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar o fornecedor.');
}

financial_registration_redirect($application, 'fornecedores.php' . ($validationFailed ? '?modal=' . ($editing ? 'edit' : 'create') : ''));
