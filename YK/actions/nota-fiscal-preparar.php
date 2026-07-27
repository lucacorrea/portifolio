<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('nota_fiscal.emitir');

try {
    $user = $application->authorization()->requireLogin();
    $result = $application->fiscalDocuments()->prepareFromServiceOrder(
        os_posted_positive_int('ordem_servico_id'),
        trim((string) ($_POST['modelo'] ?? '')),
        trim((string) ($_POST['ambiente'] ?? 'homologacao')),
        trim((string) ($_POST['idempotency_key'] ?? '')),
        $user->id()
    );
    $message = $result['created']
        ? 'Documento fiscal preparado com numeração reservada. A impressão só será liberada após autorização da SEFAZ.'
        : 'Esta solicitação fiscal já havia sido registrada; nenhum documento duplicado foi criado.';
    $session->flash('success', $message);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Fiscal document preparation failed [' . get_class($exception) . '].');
    $session->flash('danger', 'Não foi possível preparar o documento fiscal.');
}

os_redirect_back($application, 'faturamento.php');
