<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('configuracao.editar');

try {
    $user = $application->authorization()->requireLogin();
    $application->companySettings()->save($_POST, $user->id());
    $session->flash('success', 'Dados da empresa atualizados.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Company settings failed: ' . $exception->getMessage());
    $session->flash('danger', 'Nao foi possivel salvar os dados da empresa.');
}

os_redirect($application, 'configuracoes.php');
