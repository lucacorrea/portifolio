<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();

$operation = (string) ($_POST['operation'] ?? '');
$permissions = [
    'start_travel' => 'os.alterar_status',
    'start_execution' => 'os.alterar_status',
    'wait_part' => 'os.alterar_status',
    'finalize' => 'os.finalizar',
    'cancel' => 'os.cancelar',
    'reopen' => 'os.reabrir',
];
$targets = [
    'start_travel' => 'em_deslocamento',
    'start_execution' => 'em_execucao',
    'wait_part' => 'aguardando_peca',
];

[$application, $session] = os_action_context($permissions[$operation] ?? 'os.alterar_status');

try {
    $id = os_posted_positive_int('id');
    $service = $application->serviceOrderManagement();
    if ($operation === 'finalize') throw new InvalidArgumentException('Use o fluxo de finalização com execução, estoque e pagamento.');
    elseif ($operation === 'cancel') $service->cancel($id);
    elseif ($operation === 'reopen') $service->reopen($id);
    elseif (isset($targets[$operation])) $service->changeStatus($id, $targets[$operation]);
    else throw new InvalidArgumentException('Operação inválida.');
    $session->flash('success', 'Status da OS atualizado.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('OS status failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível alterar o status.');
}

os_redirect_back($application);
