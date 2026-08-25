<?php

declare(strict_types=1);

use App\Fiscal\Service\FiscalSafeLogger;

require __DIR__ . '/os-action-common.php';

os_require_post_request();

[$application, $session] =
    os_action_context(
        'nota_fiscal.emitir'
    );

try {
    $user =
        $application
        ->authorization()
        ->requireLogin();

    $documentId =
        os_posted_positive_int(
            'documento_fiscal_id'
        );

    $result =
        $application
        ->fiscalAuthorization()
        ->transmit(
            $documentId,
            $user->id()
        );

    $type =
        $result['status'] === 'autorizado'
        ? 'success'
        : (
            $result['status']
            === 'pendente_reconsulta'
            ? 'warning'
            : 'danger'
        );

    $message =
        (
            $result['cstat'] === ''
            ? ''
            : 'SEFAZ '
            . $result['cstat']
            . ': '
        )
        . $result['reason'];

    $session->flash(
        $type,
        $message
    );
} catch (
    InvalidArgumentException $exception
) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    $correlationId = FiscalSafeLogger::record($exception, 'transmit');

    $session->flash(
        'danger',
        'Não foi possível transmitir o documento fiscal. '
            . 'Referência: ' . $correlationId . '.'
    );
}

os_redirect_back(
    $application,
    'faturamento.php'
);
