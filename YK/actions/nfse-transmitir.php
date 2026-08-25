<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';
os_require_post_request();
[$application,$session] = os_action_context('nfse.emitir');
try {
    $user = $application->authorization()->requireLogin();
    $result = $application->nfseDocuments()->transmit(os_posted_positive_int('nfse_documento_id'), $user->id());
    $session->flash($result['status']==='aguardando_validacao'?'warning':'success', $result['message']);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('NFS-e transmission failed [' . get_class($exception) . '].');
    $session->flash('danger', 'A transmissão ficou inconclusiva. Não reenvie antes de verificar o protocolo/ID da DPS.');
}
os_redirect_back($application, 'faturamento.php');
