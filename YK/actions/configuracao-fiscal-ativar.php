<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
$requestedEnvironment = trim((string) ($_POST['ambiente'] ?? 'homologacao'));
$productionRequest = $requestedEnvironment === 'producao';
[$application, $session] = os_action_context(
    $productionRequest ? 'nota_fiscal.ativar_producao' : 'nota_fiscal.configurar'
);
try {
    $user = $application->authorization()->requireLogin();
    $application->fiscalConfiguration()->activate(
        os_posted_positive_int('configuracao_id'),
        (int) $user->id(),
        $productionRequest
    );
    $session->flash('success', 'Configuração fiscal de ' . $requestedEnvironment . ' ativada.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal configuration activation failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível ativar a configuração fiscal.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
