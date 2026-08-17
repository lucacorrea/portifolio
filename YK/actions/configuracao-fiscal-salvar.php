<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
$requestedEnvironment = trim((string) ($_POST['ambiente'] ?? 'homologacao'));
[$application, $session] = os_action_context(
    $requestedEnvironment === 'producao' ? 'nota_fiscal.ativar_producao' : 'nota_fiscal.configurar'
);
try {
    $user = $application->authorization()->requireLogin();
    $application->fiscalConfiguration()->createConfiguration($_POST, (int) $user->id());
    $session->flash('success', 'Nova versão da configuração de ' . $requestedEnvironment . ' criada.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal configuration save failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível salvar a configuração fiscal.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
