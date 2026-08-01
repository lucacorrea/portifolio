<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('os.excluir');

try {
    $user = $application->authorization()->requireLogin();
    $application->serviceOrderLifecycle()->softDelete(
        os_posted_positive_int('id'),
        $user->id()
    );
    $session->flash('success', 'OS excluída da operação com auditoria preservada.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('OS soft deletion failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível excluir a OS.');
}

os_redirect_back($application);
