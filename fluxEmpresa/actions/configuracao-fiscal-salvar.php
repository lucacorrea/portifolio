<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.configurar');
try {
    $user = $application->authorization()->requireLogin();
    $application->fiscalConfiguration()->createConfiguration($_POST, (int) $user->id());
    $session->flash('success', 'Nova versão da configuração de homologação criada.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal configuration save failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível salvar a configuração fiscal.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
