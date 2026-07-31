<?php
declare(strict_types=1);

require __DIR__ . '/action-common.php';
admin_post();

try {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $company = $id === false ? null : $application->adminCompanies()->find((int) $id);
    $companyIsActive = $company !== null && (string) ($company['status'] ?? '') === 'ativo';
    if (!$companyIsActive) {
        throw new InvalidArgumentException('A empresa precisa estar ativa para acessar o painel operacional.');
    }
    $session->regenerateId();
    $application->adminAccesses()->enter(
        $company,
        $currentUser->id(),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        (string) ($_POST['motivo'] ?? ''),
        $currentUser->sessionBindingHash(),
        $application->activeCompanyContext()
    );
    $session->flash('success', 'Painel operacional aberto com acesso administrativo registrado.');
    admin_action_redirect('dashboard.php');
} catch (Throwable $exception) {
    admin_action_error($exception, $id === false ? 'adm/empresas.php' : 'adm/empresa.php?id=' . $id);
}
