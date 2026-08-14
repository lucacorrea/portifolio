<?php

declare(strict_types=1);

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
    /*
     * Log técnico específico da transmissão fiscal.
     */
    $logDirectory =
        dirname(__DIR__)
        . '/storage/logs';

    if (!is_dir($logDirectory)) {
        @mkdir(
            $logDirectory,
            0755,
            true
        );
    }

    $logFile =
        $logDirectory
        . '/fiscal-emission.log';

    $logMessage = sprintf(
        "[%s]\n"
        . "ETAPA: TRANSMISSAO\n"
        . "Classe: %s\n"
        . "Mensagem: %s\n"
        . "Arquivo: %s\n"
        . "Linha: %d\n"
        . "Trace:\n%s\n"
        . "%s\n",

        date('Y-m-d H:i:s'),

        get_class(
            $exception
        ),

        $exception->getMessage(),

        $exception->getFile(),

        $exception->getLine(),

        $exception->getTraceAsString(),

        str_repeat(
            '-',
            80
        )
    );

    @file_put_contents(
        $logFile,
        $logMessage,
        FILE_APPEND | LOCK_EX
    );

    $session->flash(
        'danger',
        'Não foi possível transmitir o documento fiscal. '
        . 'O erro técnico foi registrado para análise.'
    );
}

os_redirect_back(
    $application,
    'faturamento.php'
);