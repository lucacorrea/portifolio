<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderTeamData;

require __DIR__ . '/painel-semanal-action-common.php';
os_require_post_request();
[$application, $session] = os_action_context('painel_semanal.alterar_dupla');
try {
    $application->serviceOrderManagement()->reassignTeam(os_posted_positive_int('id'), ServiceOrderTeamData::fromArray($_POST));
    $session->flash('success', 'Equipe atualizada.');
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery('team', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    painel_semanal_redirect($application, painel_semanal_return_target('team'));
} catch (Throwable $exception) {
    error_log('Weekly team failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível alterar a equipe.');
}
painel_semanal_redirect($application, painel_semanal_return_target());
