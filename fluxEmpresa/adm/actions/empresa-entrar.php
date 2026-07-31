<?php
declare(strict_types=1);

require __DIR__ . '/action-common.php';
admin_post();

try {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $company = $id === false ? null : $application->adminCompanies()->find((int) $id);
    if (!$company || !in_array((string) ($company['status'] ?? ''), ['ativo', 'pendente'], true)) {
        throw new InvalidArgumentException('A empresa precisa estar ativa ou pendente para iniciar o atendimento.');
    }
    $session->regenerateId();
    $application->adminAccesses()->enter(
        $company,
        $currentUser->id(),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        (string) ($_POST['motivo'] ?? ''),
        $application->activeCompanyContext()
    );
    $session->flash('success', 'Atendimento administrativo iniciado e registrado.');
    admin_action_redirect('adm/empresa.php?id=' . $id);
} catch (Throwable $exception) {
    admin_action_error($exception, $id === false ? 'adm/empresas.php' : 'adm/empresa.php?id=' . $id);
}
