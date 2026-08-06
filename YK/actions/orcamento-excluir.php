<?php

declare(strict_types=1);

require __DIR__ . '/orcamento-action-common.php';

budget_require_post_request();

[$application, $session] = budget_action_context(
    'orcamento.excluir'
);

$budgetId = 0;
$budgetNumber = '';

try {
    $user = $application
        ->authorization()
        ->requireLogin();

    $budgetId = budget_posted_positive_int('id');

    /*
     * Captura o número antes da exclusão para recuperar
     * corretamente a modal caso exista algum bloqueio.
     */
    $budget = $application
        ->budgetManagement()
        ->getBudget($budgetId);

    $budgetNumber = $budget->displayNumber();

    $application
        ->budgetManagement()
        ->deleteBudget(
            $budgetId,
            $user->id()
        );

    $session->flash(
        'success',
        'Orçamento excluído da operação. Os itens e o histórico foram preservados.'
    );
} catch (InvalidArgumentException $exception) {
    budget_store_form_recovery(
        'delete',
        [
            'id' => $budgetId,
            'number' => $budgetNumber,
        ],
        $exception->getMessage()
    );

    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'Budget soft deletion failed: '
        . $exception->getMessage()
    );

    $message =
        'Não foi possível excluir o orçamento. Nenhuma alteração parcial foi mantida.';

    budget_store_form_recovery(
        'delete',
        [
            'id' => $budgetId,
            'number' => $budgetNumber,
        ],
        $message
    );

    $session->flash(
        'danger',
        $message
    );
}

budget_redirect($application);