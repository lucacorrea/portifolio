<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.emitir');

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->fiscalAuthorization()->transmit(
        os_posted_positive_int('documento_fiscal_id'),
        $user->id()
    );
    $type = $result['status'] === 'autorizado' ? 'success' : ($result['status'] === 'pendente_reconsulta' ? 'warning' : 'danger');
    $session->flash($type, ($result['cstat'] === '' ? '' : 'SEFAZ ' . $result['cstat'] . ': ') . $result['reason']);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal transmission action failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível transmitir o documento fiscal. Consulte o histórico antes de repetir.');
}

os_redirect_back($application, 'faturamento.php');
