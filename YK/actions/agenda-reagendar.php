<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderScheduleData;

require __DIR__ . '/agenda-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('agenda.reagendar');
try {
    $schedule = ServiceOrderScheduleData::fromArray($_POST);
    if ($schedule === null) throw new InvalidArgumentException('Informe o agendamento.');
    $application->serviceOrderManagement()->reschedule(os_posted_positive_int('id'), $schedule);
    $session->flash('success', 'OS reagendada.');
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('reschedule', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    agenda_redirect($application, 'agenda.php?modal=reschedule');
} catch (Throwable $exception) {
    error_log('Agenda reschedule failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível reagendar.');
}
agenda_redirect($application);
