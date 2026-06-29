<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('os.cancelar');

try {
    $user = $application->authorization()->requireLogin();
    $application->serviceOrderManagement()->cancelWithDetails(
        os_posted_positive_int('id'),
        (string) ($_POST['opcao'] ?? ''),
        (string) ($_POST['motivo'] ?? ''),
        isset($_POST['observacao']) ? (string) $_POST['observacao'] : null,
        $user->id()
    );
    $session->flash('success', 'OS cancelada com auditoria registrada.');
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('cancel', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    os_redirect($application, 'ordens-servico.php?modal=cancel');
} catch (Throwable $exception) {
    error_log('OS cancel failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível cancelar a OS.');
}

os_redirect($application);
