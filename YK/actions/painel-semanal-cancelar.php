<?php

declare(strict_types=1);

require __DIR__ . '/painel-semanal-action-common.php';
os_require_post_request();
[$application, $session] = os_action_context('painel_semanal.cancelar');
try {
    $application->serviceOrderManagement()->cancel(os_posted_positive_int('id'));
    $session->flash('success', 'OS cancelada.');
} catch (Throwable $exception) {
    error_log('Weekly cancel failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível cancelar.');
}
painel_semanal_redirect($application);
