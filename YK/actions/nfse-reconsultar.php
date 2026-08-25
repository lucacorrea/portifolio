<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';
os_require_post_request();
[$application,$session] = os_action_context('nfse.reconsultar');
try {
    $user = $application->authorization()->requireLogin();
    $result = $application->nfseDocuments()->reconcile(os_posted_positive_int('nfse_documento_id'), $user->id());
    $session->flash($result['status']==='autorizado'?'success':($result['status']==='aguardando_validacao'?'warning':'danger'), $result['message']);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('NFS-e reconciliation failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível consultar o protocolo NFS-e agora.');
}
os_redirect_back($application, 'faturamento.php');
