<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('os.finalizar');
$application->authorization()->requirePermission('os.finalizar_com_pagamento');

try {
    $user = $application->authorization()->requireLogin();
    $application->serviceOrderFinalization()->finalize(
        os_posted_positive_int('id'),
        $_POST,
        $user->id()
    );
    $session->flash('success', 'OS finalizada com execução, estoque e financeiro registrados.');
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('finalize', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    os_redirect($application, 'ordens-servico.php?modal=finalize');
} catch (Throwable $exception) {
    error_log('OS finalization failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível finalizar a OS.');
}

os_redirect($application);
