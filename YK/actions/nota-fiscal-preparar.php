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

    $orderId = os_posted_positive_int(
        'ordem_servico_id'
    );

    $model = trim(
        (string) ($_POST['modelo'] ?? '')
    );

    $environment = trim(
        (string) ($_POST['ambiente'] ?? 'homologacao')
    );

    $idempotencyKey = trim(
        (string) ($_POST['idempotency_key'] ?? '')
    );

    $prepared = $application
        ->fiscalDocuments()
        ->prepareFromServiceOrder(
            $orderId,
            $model,
            $environment,
            $idempotencyKey,
            $user->id()
        );

    $documentId = (int) ($prepared['id'] ?? 0);

    if ($documentId <= 0) {
        throw new InvalidArgumentException(
            'O documento fiscal não pôde ser preparado.'
        );
    }

    $result = $application
        ->fiscalAuthorization()
        ->transmit(
            $documentId,
            $user->id()
        );

    $status = trim(
        (string) ($result['status'] ?? '')
    );

    $cstat = trim(
        (string) ($result['cstat'] ?? '')
    );

    $reason = trim(
        (string) ($result['reason'] ?? '')
    );

    if ($status === 'autorizado') {
        $session->flash(
            'success',
            'Documento fiscal autorizado pela SEFAZ'
            . ($cstat !== '' ? ' (' . $cstat . ')' : '')
            . '. A impressão válida foi liberada.'
        );
    } elseif ($status === 'pendente_reconsulta') {
        $session->flash(
            'warning',
            $reason !== ''
                ? $reason
                : 'A transmissão ficou pendente de reconsulta na SEFAZ.'
        );
    } else {
        $session->flash(
            'danger',
            'SEFAZ '
            . ($cstat !== '' ? $cstat : '-')
            . ': '
            . (
                $reason !== ''
                    ? $reason
                    : 'Documento fiscal não autorizado.'
            )
        );
    }
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    $correlationId = FiscalSafeLogger::record(
        $exception,
        'prepare_and_transmit'
    );

    $session->flash(
        'danger',
        'Não foi possível concluir a emissão fiscal. '
        . 'Referência: '
        . $correlationId
        . '.'
    );
}

os_redirect_back(
    $application,
    'faturamento.php'
);
