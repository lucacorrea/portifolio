<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderFormData;
use App\ServiceOrder\DTO\ServiceOrderScheduleData;
use App\ServiceOrder\DTO\ServiceOrderTeamData;

require __DIR__ . '/os-action-common.php';

os_require_post_request();

$isEditing = trim((string) ($_POST['id'] ?? '')) !== '';
$teamSubmitted = isset($_POST['team_submitted']);
$scheduleSubmitted = trim((string) ($_POST['agendado_inicio'] ?? '')) !== ''
    || trim((string) ($_POST['agendado_fim'] ?? '')) !== '';
[$application, $session] = os_action_context($isEditing ? 'os.editar' : 'os.criar');

try {
    $payload = $_POST;
    $payload['items'] = os_items_from_post();
    $service = $application->serviceOrderManagement();
    if ($teamSubmitted) {
        $application->authorization()->requirePermission('os.alterar_equipe');
    }
    if ($scheduleSubmitted) {
        $application->authorization()->requirePermission('os.agendar');
    }
    $team = $teamSubmitted ? ServiceOrderTeamData::fromArray($_POST) : null;
    $schedule = $scheduleSubmitted ? ServiceOrderScheduleData::fromArray($_POST) : null;

    if ($isEditing) {
        $currentOrder = $service->getOrder(os_posted_positive_int('id'));
        $payload['status'] = $currentOrder->status();
        if ($currentOrder->budgetId() !== null) {
            $payload['budget_id'] = $currentOrder->budgetId();
        }
        $data = ServiceOrderFormData::fromArray($payload, true);
        $service->updateOrder(
            id: $currentOrder->id(),
            data: $data,
            team: $team,
            schedule: $schedule,
            teamSubmitted: $teamSubmitted,
            scheduleSubmitted: $scheduleSubmitted
        );
        $session->flash('success', 'OS atualizada com sucesso.');
    } elseif (($payload['creation_mode'] ?? 'manual') === 'budget') {
        $application->authorization()->requirePermission('orcamento.converter_os');
        $order = $service->createOrderFromApprovedBudget(
            os_posted_positive_int('budget_id'),
            $team,
            $schedule,
            trim((string) ($payload['save_as_draft'] ?? '')) !== ''
        );
        $session->flash('success', 'OS cadastrada a partir do orçamento com o número ' . $order->displayNumber() . '.');
    } else {
        $data = ServiceOrderFormData::fromArray($payload, false);
        $order = $service->createOrder($data, $team, $schedule);
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
