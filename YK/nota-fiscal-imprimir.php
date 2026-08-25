<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;

$app =
    require __DIR__ . '/bootstrap.php';

/** @var Application $application */
$application =
    $app['application'];

$session =
    $application->session();

$session->start();

/*
|--------------------------------------------------------------------------
| Segurança de resposta
|--------------------------------------------------------------------------
*/

header(
    'Cache-Control: private, no-store, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'X-Content-Type-Options: nosniff'
);

/*
|--------------------------------------------------------------------------
| Autenticação e autorização
|--------------------------------------------------------------------------
*/

try {
    $authorization =
        $application
            ->authorization();

    $user =
        $authorization
            ->requireLogin();

    $authorization
        ->requirePermission(
            'nota_fiscal.visualizar'
        );
} catch (AuthenticationException) {
    header(
        'Location: login.php',
        true,
        303
    );

    exit;
} catch (AuthorizationException) {
    header(
        'Location: acesso-negado.php',
        true,
        303
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Documento fiscal
|--------------------------------------------------------------------------
*/

$id =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

if (!is_int($id)) {
    http_response_code(404);

    exit(
        'Documento fiscal não encontrado.'
    );
}

/*
|--------------------------------------------------------------------------
| Renderização
|--------------------------------------------------------------------------
*/

try {
    $document =
        $application
            ->fiscalDocumentPrinter()
            ->renderAuthorized(
                $id
            );

    /*
     * Auditoria de reimpressão.
     */
    $application
        ->fiscalDocuments()
        ->recordAccess(
            $id,
            'reimpressao',
            $user->id()
        );

    $pdf =
        (string) (
            $document['pdf']
            ?? ''
        );

    $filename =
        basename(
            (string) (
                $document['filename']
                ?? 'documento-fiscal.pdf'
            )
        );

    if (
        $pdf === ''
        || !str_starts_with(
            $pdf,
            '%PDF-'
        )
    ) {
        throw new RuntimeException(
            'O PDF fiscal gerado é inválido.'
        );
    }

    /*
     * Evita caracteres inadequados no header.
     */
    $filename =
        preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            $filename
        ) ?: 'documento-fiscal.pdf';

    header(
        'Content-Type: application/pdf'
    );

    header(
        'Content-Disposition: inline; filename="'
        . $filename
        . '"'
    );

    header(
        'Content-Length: '
        . strlen($pdf)
    );

    echo $pdf;

    exit;
} catch (InvalidArgumentException $exception) {
    http_response_code(409);

    exit(
        htmlspecialchars(
            $exception->getMessage(),
            ENT_QUOTES
            | ENT_SUBSTITUTE,
            'UTF-8'
        )
    );
} catch (Throwable $exception) {
    /*
     * Não expõe caminho, stack trace ou XML fiscal ao navegador.
     */
    error_log(
        sprintf(
            'Fiscal PDF rendering failed [%s]: %s',
            get_class($exception),
            $exception->getMessage()
        )
    );

    http_response_code(500);

    exit(
        'Não foi possível gerar o documento auxiliar fiscal.'
    );
}
