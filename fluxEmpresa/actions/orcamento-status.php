<?php

declare(strict_types=1);

require __DIR__ . '/orcamento-action-common.php';

budget_require_post_request();

$operation = (string) ($_POST['operation'] ?? '');
$permission = match ($operation) {
    'approve' => 'orcamento.aprovar',
    'reject' => 'orcamento.recusar',
    default => '',
};

if ($permission === '') {
    http_response_code(400);
    exit;
}

[$application, $session] = budget_action_context($permission);

try {
    $budgetId = budget_posted_positive_int('id');
    if ($operation === 'approve') {
        $order = $application->serviceOrderManagement()->approveBudgetAndCreateOrder($budgetId);
        $session->flash(
            'success',
            'Orçamento aprovado e OS criada automaticamente: ' . $order->displayNumber() . '.'
        );
        if ($application->authorization()->can('os.visualizar')) {
            $target = 'ordens-servico.php?search=' . rawurlencode($order->displayNumber());
            header('Location: ' . $application->redirect()->applicationUrl($target), true, 303);
            exit;
        }
    } else {
        $application->budgetManagement()->rejectBudget($budgetId, $_POST['reason'] ?? null);
        $session->flash('success', 'Orçamento recusado com sucesso.');
    }
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Budget status failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível alterar o status do orçamento.');
}

budget_redirect($application, 'orcamentos.php');
