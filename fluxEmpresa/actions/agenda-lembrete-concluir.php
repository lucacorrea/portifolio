<?php

declare(strict_types=1);

require __DIR__ . '/agenda-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('agenda.editar');
$redirectTarget = agenda_return_target();

try {
    $user = $application->authorization()->requireLogin();
    $application->agendaManagement()->completeReminder(
        os_posted_positive_int('id'),
        $user->id()
    );
    $session->flash('success', 'Compromisso marcado como feito.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Reminder completion failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível marcar o compromisso como feito.');
}

agenda_redirect($application, $redirectTarget);
