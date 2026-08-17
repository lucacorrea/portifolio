<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application,$session] = os_action_context('nfse.emitir');
try {
    $user = $application->authorization()->requireLogin();
    $documents = $application->nfseDocuments()->prepareFromServiceOrder(
        os_posted_positive_int('ordem_servico_id'),
        trim((string)($_POST['ambiente'] ?? 'homologacao')),
        trim((string)($_POST['idempotency_key'] ?? '')),
        $user->id()
    );
    $session->flash('success', count($documents) . ' DPS preparada(s). Revise e transmita cada grupo fiscal.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('NFS-e preparation failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível preparar a NFS-e.');
}
os_redirect_back($application, 'faturamento.php');
