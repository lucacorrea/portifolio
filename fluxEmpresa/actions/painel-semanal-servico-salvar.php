<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderFormData;
use App\ServiceOrder\DTO\ServiceOrderScheduleData;
use App\ServiceOrder\DTO\ServiceOrderTeamData;

require __DIR__ . '/painel-semanal-action-common.php';

os_require_post_request();

[$application, $session] = os_action_context('painel_semanal.adicionar');
$redirectTarget = painel_semanal_return_target();

try {
    $serviceId = os_positive_int($_POST['service_id'] ?? null);
    $service = $application->serviceManagement()->getService($serviceId);
    if ($service->status() !== 'ativo') {
        throw new InvalidArgumentException('Serviço selecionado está inativo.');
    }

    $payload = $_POST;
    $payload['items'] = [[
        'type' => 'servico',
        'reference_id' => $service->id(),
        'description' => $service->name(),
        'unit' => 'un',
        'quantity' => '1',
        'unit_price' => $service->value(),
        'discount' => '0',
    ]];

    $schedule = ServiceOrderScheduleData::fromArray($payload);
    $teamData = ServiceOrderTeamData::fromArray($payload);
    $team = $teamData->hasMembers() ? $teamData : null;
    $payload['status'] = $schedule === null ? 'aguardando_agendamento' : 'agendada';

    $order = $application->serviceOrderManagement()->createOrder(
        ServiceOrderFormData::fromArray($payload),
        $team,
        $schedule
    );

    $session->flash(
        'success',
        $schedule === null
            ? $order->displayNumber() . ' criada e aguardando agendamento.'
            : 'Serviço agendado no painel como ' . $order->displayNumber() . '.'
    );
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('create', $_POST, $exception->getMessage());
    $redirectTarget = painel_semanal_return_target('create');
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Weekly service create failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível adicionar o serviço ao painel.');
}

painel_semanal_redirect($application, $redirectTarget);
