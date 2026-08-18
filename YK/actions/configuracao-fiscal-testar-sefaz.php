<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
$requestedEnvironment = trim((string) ($_POST['ambiente'] ?? 'homologacao'));
$productionRequest = $requestedEnvironment === 'producao';
[$application, $session] = os_action_context(
    $productionRequest ? 'nota_fiscal.ativar_producao' : 'nota_fiscal.testar_integracao'
);
try {
    $user = $application->authorization()->requireLogin();
    $status = $application->fiscalSefazConnection()->test(
        os_posted_positive_int('configuracao_id'),
        (int) $user->id(),
        $productionRequest
    );
    $session->flash(
        'success',
        'SEFAZ ' . $requestedEnvironment . ' disponível (' . $status['code'] . '): ' . $status['message'] . '.'
    );
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal SEFAZ test action failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível testar a comunicação com a SEFAZ.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
