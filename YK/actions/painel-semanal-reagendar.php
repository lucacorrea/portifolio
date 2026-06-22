<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderScheduleData;

require __DIR__ . '/painel-semanal-action-common.php';
os_require_post_request();
[$application, $session] = os_action_context('painel_semanal.alterar_horario');
try {
    $schedule = ServiceOrderScheduleData::fromArray($_POST);
    if ($schedule === null) throw new InvalidArgumentException('Informe o agendamento.');
    $application->serviceOrderManagement()->reschedule(os_posted_positive_int('id'), $schedule);
    $session->flash('success', 'Horário atualizado.');
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('reschedule', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    painel_semanal_redirect($application, painel_semanal_return_target('reschedule'));
} catch (Throwable $exception) {
    error_log('Weekly reschedule failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível alterar horário.');
}
painel_semanal_redirect($application, painel_semanal_return_target());
