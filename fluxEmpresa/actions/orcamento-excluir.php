<?php

declare(strict_types=1);

require __DIR__ . '/orcamento-action-common.php';

budget_require_post_request();
[$application, $session] = budget_action_context('orcamento.excluir');

try {
    $user = $application->authorization()->requireLogin();
    $application->budgetManagement()->deleteBudget(
        budget_posted_positive_int('id'),
        $user->id()
    );
    $session->flash('success', 'Orçamento excluído com os itens e o histórico preservados.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Budget soft deletion failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível excluir o orçamento.');
}

budget_redirect($application);
