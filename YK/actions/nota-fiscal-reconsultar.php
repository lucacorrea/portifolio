<?php

declare(strict_types=1);

use App\Fiscal\Service\FiscalSafeLogger;

require __DIR__ . '/os-action-common.php';

os_require_post_request();

[$application, $session] = os_action_context(
    'nota_fiscal.emitir'
);

try {
    $user = $application
        ->authorization()
        ->requireLogin();

    $documentId = os_posted_positive_int(
        'documento_fiscal_id'
    );

    $result = $application
        ->fiscalAuthorization()
        ->reconcile(
            $documentId,
            $user->id()
        );

    $status = trim((string) ($result['status'] ?? ''));
    $cstat = trim((string) ($result['cstat'] ?? ''));
    $reason = trim((string) ($result['reason'] ?? ''));

    $type = $status === 'autorizado'
        ? 'success'
        : ($status === 'pendente_reconsulta' ? 'warning' : 'danger');

    $message = $cstat !== ''
        ? 'SEFAZ ' . $cstat . ': '
        : '';

    $message .= $reason !== ''
        ? $reason
        : 'A reconsulta não retornou uma situação fiscal conclusiva.';

    $session->flash(
        $type,
        $message
    );
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    $correlationId = FiscalSafeLogger::record(
        $exception,
        'authorization_requery_action'
    );

    $session->flash(
        'danger',
        'Não foi possível reconsultar o documento fiscal agora. '
        . 'Referência: '
        . $correlationId
        . '.'
    );
}

os_redirect_back(
    $application,
    'faturamento.php'
);
