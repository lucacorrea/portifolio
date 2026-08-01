<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.emitir');

try {
    $user = $application->authorization()->requireLogin();
    $prepared = $application->fiscalDocuments()->prepareFromServiceOrder(
        os_posted_positive_int('ordem_servico_id'),
        trim((string) ($_POST['modelo'] ?? '')),
        trim((string) ($_POST['ambiente'] ?? 'homologacao')),
        trim((string) ($_POST['idempotency_key'] ?? '')),
        $user->id()
    );
    $result = $application->fiscalAuthorization()->transmit((int) $prepared['id'], $user->id());
    if ($result['status'] === 'autorizado') {
        $session->flash('success', 'Documento fiscal autorizado pela SEFAZ (' . $result['cstat'] . '). A impressão válida foi liberada.');
    } elseif ($result['status'] === 'pendente_reconsulta') {
        $session->flash('warning', $result['reason']);
    } else {
        $session->flash('danger', 'SEFAZ ' . ($result['cstat'] ?: '-') . ': ' . $result['reason']);
    }
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal document emission failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível concluir a emissão fiscal. Consulte o histórico antes de tentar novamente.');
}

os_redirect_back($application, 'faturamento.php');
