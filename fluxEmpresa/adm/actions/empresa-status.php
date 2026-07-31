<?php
declare(strict_types=1);

require __DIR__ . '/action-common.php';
admin_post();

try {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || $application->adminCompanies()->find((int) $id) === null) {
        throw new InvalidArgumentException('Empresa não encontrada.');
    }
    $status = (string) ($_POST['status'] ?? '');
    $application->adminCompanies()->status((int) $id, $status);
    $active = $application->activeCompanyContext()->current();
    if ($active !== null && $active->id === (int) $id && in_array($status, ['inativo', 'bloqueado'], true)) {
        $application->adminAccesses()->leaveAuthorized(
            $application->activeCompanyContext(),
            $currentUser->id(),
            $currentUser->sessionBindingHash()
        );
    }
    $session->flash('success', 'Status atualizado.');
    admin_action_redirect('adm/empresa.php?id=' . $id);
} catch (Throwable $exception) {
    admin_action_error($exception, 'adm/empresas.php');
}
