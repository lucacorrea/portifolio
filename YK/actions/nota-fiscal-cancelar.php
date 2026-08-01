<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.cancelar');

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->fiscalAuthorization()->cancel(
        os_posted_positive_int('documento_fiscal_id'),
        trim((string) ($_POST['justificativa'] ?? '')),
        $user->id()
    );
    $type = $result['status'] === 'cancelado' ? 'success' : 'danger';
    $session->flash($type, 'SEFAZ ' . ($result['cstat'] ?: '-') . ': ' . $result['reason']);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal cancellation action failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível concluir o cancelamento fiscal. Consulte a situação na SEFAZ.');
}

os_redirect_back($application, 'faturamento.php');
