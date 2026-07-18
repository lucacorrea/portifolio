<?php

declare(strict_types=1);

require __DIR__ . '/financial-registration-action-common.php';

financial_registration_require_post();
[$application, $session] = financial_registration_context('fornecedor.desativar', 'fornecedores.php');

try {
    $status = trim((string) ($_POST['status'] ?? ''));
    $application->supplierManagement()->setStatus(
        financial_registration_positive_int($_POST['id'] ?? null),
        $status
    );
    $session->flash('success', $status === 'ativo' ? 'Fornecedor ativado com sucesso.' : 'Fornecedor desativado com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Supplier status change failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível alterar o fornecedor.');
}

financial_registration_redirect($application, 'fornecedores.php');
