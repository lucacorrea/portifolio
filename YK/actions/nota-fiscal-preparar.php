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

    $prepared =
        $application
        ->fiscalDocuments()
        ->prepareFromServiceOrder(
            os_posted_positive_int(
                'ordem_servico_id'
            ),

            trim(
                (string) (
                    $_POST['modelo']
                    ?? ''
                )
            ),

            trim(
                (string) (
                    $_POST['ambiente']
                    ?? 'homologacao'
                )
            ),

            trim(
                (string) (
                    $_POST['idempotency_key']
                    ?? ''
                )
            ),

            $user->id()
        );

    $result =
        $application
        ->fiscalAuthorization()
        ->transmit(
            (int) $prepared['id'],
            $user->id()
        );

    if (
        $result['status']
        === 'autorizado'
    ) {
        $session->flash(
            'success',
            'Documento fiscal autorizado pela SEFAZ ('
                . $result['cstat']
                . '). A impressão válida foi liberada.'
        );
    } elseif (
        $result['status']
        === 'pendente_reconsulta'
    ) {
        $session->flash(
            'warning',
            $result['reason']
        );
    } else {
        $session->flash(
            'danger',
            'SEFAZ '
                . (
                    $result['cstat']
                    ?: '-'
                )
                . ': '
                . $result['reason']
        );
    }
} catch (
    InvalidArgumentException $exception
) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    /*
     * Log fiscal próprio.
     * Não exibe detalhes técnicos ao usuário.
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
        'Não foi possível concluir a emissão fiscal. '
            . 'O erro técnico foi registrado para análise.'
    );
}

os_redirect_back(
    $application,
    'faturamento.php'
);
