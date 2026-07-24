<?php

declare(strict_types=1);

require __DIR__ . '/painel-semanal-action-common.php';
os_require_post_request();
[$application, $session] = os_action_context('painel_semanal.cancelar');
$redirectTarget = painel_semanal_return_target();
try {
    $application->serviceOrderManagement()->cancel(os_posted_positive_int('id'));
    $session->flash('success', 'OS cancelada.');
} catch (Throwable $exception) {
    os_store_form_recovery('cancel', $_POST, 'Não foi possível cancelar.');
    $redirectTarget = painel_semanal_return_target('cancel');
    error_log('Weekly cancel failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível cancelar.');
}
painel_semanal_redirect($application, $redirectTarget);
