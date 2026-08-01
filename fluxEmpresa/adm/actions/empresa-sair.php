<?php
declare(strict_types=1);

require __DIR__ . '/action-common.php';
admin_post();

try {
    $active = $application->activeCompanyContext()->current();
    $application->adminAccesses()->leaveAuthorized(
        $application->activeCompanyContext(),
        $currentUser->id(),
        $currentUser->sessionBindingHash()
    );
    $session->regenerateId();
    $session->flash('success', 'Acesso administrativo encerrado.');
    admin_action_redirect('adm/' . ($active ? 'empresa.php?id=' . $active->id : 'empresas.php'));
} catch (\Throwable $exception) {
    $application->activeCompanyContext()->clear();
    admin_action_error($exception, 'adm/empresas.php');
}
