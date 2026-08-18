<?php

declare(strict_types=1);

use App\Fiscal\Service\FiscalSafeLogger;

require __DIR__ . '/os-action-common.php';

os_require_post_request();
$environment = trim((string) ($_POST['ambiente'] ?? 'homologacao'));
$production = $environment === 'producao';
[$application, $session] = os_action_context(
    'nota_fiscal.inutilizar'
);
try {
    $user = $application->authorization()->requireLogin();
    if ($production) {
        $application->authorization()->requirePermission('nota_fiscal.ativar_producao');
    }
    $result = $application->fiscalInutilization()->request($_POST, (int) $user->id(), $production);
    $type = $result['status'] === 'autorizado'
        ? 'success'
        : ($result['status'] === 'pendente_confirmacao' ? 'warning' : 'danger');
    $session->flash(
        $type,
        ($result['cstat'] === '' ? '' : 'SEFAZ ' . $result['cstat'] . ': ') . $result['reason']
    );
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    $reference = FiscalSafeLogger::record($exception, 'inutilization_action');
    $session->flash('danger', 'Não foi possível concluir a inutilização. Referência: ' . $reference . '.');
}
os_redirect_back($application, 'configuracoes-fiscais.php');
