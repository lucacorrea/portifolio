<?php

declare(strict_types=1);

use App\Schedule\DTO\AgendaReminderFormData;

require __DIR__ . '/agenda-action-common.php';

os_require_post_request();
$isEditing = trim((string) ($_POST['id'] ?? '')) !== '';
[$application, $session] = os_action_context($isEditing ? 'agenda.editar' : 'agenda.criar_lembrete');

try {
    $data = AgendaReminderFormData::fromArray($_POST);
    if ($isEditing) $application->agendaManagement()->updateReminder(os_posted_positive_int('id'), $data);
    else $application->agendaManagement()->createReminder($data);
    $session->flash('success', 'Lembrete salvo.');
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('reminder', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    agenda_redirect($application, 'agenda.php?modal=reminder');
} catch (Throwable $exception) {
    error_log('Reminder save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar o lembrete.');
}

agenda_redirect($application);
