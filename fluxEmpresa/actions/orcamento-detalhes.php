<?php

declare(strict_types=1);

require __DIR__ . '/orcamento-action-common.php';

header('Content-Type: application/json; charset=utf-8');

try {
    [$application] = budget_action_context('orcamento.visualizar', false);
    $budgetId = budget_positive_int($_GET['id'] ?? null);
    $forEdit = ($_GET['mode'] ?? '') === 'edit';

    if ($forEdit) {
        $application->authorization()->requirePermission('orcamento.editar');
    }

    $service = $application->budgetManagement();
    $budget = $service->getBudget($budgetId);
    $items = $service->getBudgetItems($budgetId);

    echo json_encode([
        'budget' => [
            'id' => $budget->id(),
            'number' => $budget->displayNumber(),
            'client_id' => $budget->clientId(),
            'client_code' => $budget->clientCode(),
            'client_name' => $budget->clientName(),
            'client_document' => $budget->clientDocument(),
            'issue_date' => $budget->issueDate(),
            'valid_until' => $budget->validUntil(),
            'status' => $budget->status(),
            'display_status' => $budget->displayStatus(),
            'notes' => $budget->notes(),
            'rejection_reason' => $budget->rejectionReason(),
            'services_subtotal' => $budget->servicesSubtotal(),
            'products_subtotal' => $budget->productsSubtotal(),
            'others_subtotal' => $budget->othersSubtotal(),
            'discount' => $budget->discount(),
            'increase' => $budget->increase(),
            'total' => $budget->total(),
            'created_at' => $budget->createdAt(),
            'updated_at' => $budget->updatedAt(),
        ],
        'items' => array_map(static fn($item): array => [
            'id' => $item->id(),
            'type' => $item->type(),
            'reference_id' => $item->referenceId(),
            'description' => $item->description(),
            'unit' => $item->unit(),
            'quantity' => $item->quantity(),
            'unit_price' => $item->unitPrice(),
            'discount' => $item->discount(),
            'subtotal' => $item->subtotal(),
        ], $items),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Budget details failed: ' . $exception->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Não foi possível carregar o orçamento.']);
}
