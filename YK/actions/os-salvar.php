<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderFormData;

require __DIR__ . '/os-action-common.php';

os_require_post_request();

$isEditing = trim((string) ($_POST['id'] ?? '')) !== '';
[$application, $session] = os_action_context($isEditing ? 'os.editar' : 'os.criar');

try {
    $payload = $_POST;
    $payload['items'] = os_items_from_post();
    $service = $application->serviceOrderManagement();

    if ($isEditing) {
        $currentOrder = $service->getOrder(os_posted_positive_int('id'));
        $payload['status'] = $currentOrder->status();
        if ($currentOrder->budgetId() !== null) {
            $payload['budget_id'] = $currentOrder->budgetId();
        }
        $data = ServiceOrderFormData::fromArray($payload, true);
        $service->updateOrder($currentOrder->id(), $data);
        $session->flash('success', 'OS atualizada com sucesso.');
    } elseif (($payload['creation_mode'] ?? 'manual') === 'budget') {
        $application->authorization()->requirePermission('orcamento.converter_os');
        $order = $service->createOrderFromApprovedBudget(
            os_posted_positive_int('budget_id'),
            os_optional_team_from_post(),
            os_optional_schedule_from_post(),
            trim((string) ($payload['save_as_draft'] ?? '')) !== ''
        );
        $session->flash('success', 'OS cadastrada a partir do orçamento com o número ' . $order->displayNumber() . '.');
    } else {
        $data = ServiceOrderFormData::fromArray($payload, false);
        $order = $service->createOrder($data, os_optional_team_from_post(), os_optional_schedule_from_post());
        $session->flash('success', 'OS cadastrada com o número ' . $order->displayNumber() . '.');
    }
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery($isEditing ? 'edit' : 'create', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    os_redirect_back($application, 'ordens-servico.php', ['modal' => $isEditing ? 'edit' : 'create']);
} catch (Throwable $exception) {
    error_log('OS save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar a OS.');
}

os_redirect_back($application);
