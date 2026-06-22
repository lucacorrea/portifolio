<?php

declare(strict_types=1);

require __DIR__ . '/agenda-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('agenda.cancelar');
$redirectTarget = agenda_return_target();
try {
    $application->agendaManagement()->cancelReminder(os_posted_positive_int('id'));
    $session->flash('success', 'Lembrete cancelado.');
} catch (Throwable $exception) {
    os_store_form_recovery('cancel', $_POST, 'Não foi possível cancelar o lembrete.');
    $redirectTarget = agenda_return_target('cancel');
    error_log('Reminder cancel failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível cancelar o lembrete.');
}
agenda_redirect($application, $redirectTarget);
