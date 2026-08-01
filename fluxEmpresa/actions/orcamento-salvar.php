<?php

declare(strict_types=1);

use App\Sales\DTO\BudgetFormData;

require __DIR__ . '/orcamento-action-common.php';

budget_require_post_request();

$rawBudgetId = trim((string) ($_POST['id'] ?? ''));
$isEditing = $rawBudgetId !== '';
$requiredPermission = $isEditing ? 'orcamento.editar' : 'orcamento.criar';
[$application, $session] = budget_action_context($requiredPermission);

try {
    $budgetId = $isEditing ? budget_posted_positive_int('id') : null;
    $payload = $_POST;
    $payload['items'] = budget_items_from_post();
    $data = BudgetFormData::fromArray($payload);
    $service = $application->budgetManagement();

    if ($budgetId === null) {
        $budget = $service->createBudget($data);
        $session->flash('success', 'Orçamento cadastrado com o número ' . $budget->displayNumber() . '.');
    } else {
        $service->updateBudget($budgetId, $data);
        $session->flash('success', 'Orçamento atualizado com sucesso.');
    }
} catch (InvalidArgumentException $exception) {
    budget_store_form_recovery($isEditing ? 'edit' : 'create', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    budget_redirect($application, 'orcamentos.php?modal=' . ($isEditing ? 'edit' : 'create'));
} catch (Throwable $exception) {
    error_log('Budget save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar o orçamento.');
}

budget_redirect($application, 'orcamentos.php');
