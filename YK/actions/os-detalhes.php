<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

header('Content-Type: application/json; charset=utf-8');

try {
    [$application] = os_action_context('os.visualizar', false);
    $canViewValues = $application->authorization()->can('os.visualizar_valores');
    $order = $application->serviceOrderManagement()->getOrder(os_positive_int($_GET['id'] ?? null));
    $items = $application->serviceOrderManagement()->getOrderItems($order->id());
    $team = $application->serviceOrderManagement()->getOrderTeamMembers($order->id());
    $payload = [
        'order' => [
            'id' => $order->id(),
            'number' => $order->displayNumber(),
            'client_id' => $order->clientId(),
            'client_name' => $order->clientName(),
            'equipment_type' => $order->equipmentType(),
            'equipment_brand' => $order->equipmentBrand(),
            'equipment_model' => $order->equipmentModel(),
            'equipment_capacity' => $order->equipmentCapacity(),
            'equipment_serial_number' => $order->equipmentSerialNumber(),
            'equipment_environment' => $order->equipmentEnvironment(),
            'equipment_location' => $order->equipmentLocation(),
            'reported_problem' => $order->reportedProblem(),
            'identified_problem' => $order->identifiedProblem(),
            'diagnosis' => $order->diagnosis(),
            'solution' => $order->solution(),
            'recommendation' => $order->recommendation(),
            'internal_notes' => $order->internalNotes(),
            'notes' => $order->notes(),
            'primary_employee_id' => $order->primaryEmployeeId(),
            'support_employee_id' => $order->supportEmployeeId(),
            'scheduled_start' => $order->scheduledStart(),
            'scheduled_end' => $order->scheduledEnd(),
            'status' => $order->status(),
            'priority' => $order->priority(),
        ],
        'items' => array_map(static fn($item): array => [
            'id' => $item->id(), 'type' => $item->type(), 'origin' => $item->origin(), 'reference_id' => $item->referenceId(), 'budget_item_id' => $item->budgetItemId(), 'description' => $item->description(),
            'unit' => $item->unit(), 'quantity' => $item->quantity(), 'unit_price' => $item->unitPrice(), 'discount' => $item->discount(), 'subtotal' => $item->subtotal(),
        ], $items),
        'team' => array_map(static fn($member): array => [
            'employee_id' => $member->employeeId(),
            'role' => $member->role(),
            'primary' => $member->primary(),
            'display' => $member->displayLine(),
        ], $team),
    ];
    if ($canViewValues) {
        $payload['order'] += ['services_subtotal' => $order->servicesSubtotal(), 'products_subtotal' => $order->productsSubtotal(), 'others_subtotal' => $order->othersSubtotal(), 'discount' => $order->discount(), 'increase' => $order->increase(), 'total' => $order->total()];
    }
    echo json_encode($payload, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('OS details failed: ' . $exception->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Não foi possível carregar a OS.']);
}
