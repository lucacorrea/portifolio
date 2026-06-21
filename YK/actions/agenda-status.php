<?php

declare(strict_types=1);

require __DIR__ . '/agenda-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('agenda.editar');
try {
    $operation = (string) ($_POST['operation'] ?? '');
    $map = ['start_travel' => 'em_deslocamento', 'start_execution' => 'em_execucao', 'wait_part' => 'aguardando_peca'];
    if ($operation === 'cancel') {
        $application->authorization()->requirePermission('agenda.cancelar');
        $application->serviceOrderManagement()->cancel(os_posted_positive_int('id'));
    } elseif (isset($map[$operation])) {
        $application->serviceOrderManagement()->changeStatus(os_posted_positive_int('id'), $map[$operation]);
    } else {
        throw new InvalidArgumentException('Operação inválida.');
    }
    $session->flash('success', 'Status atualizado.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Agenda status failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível alterar o status.');
}
agenda_redirect($application);
