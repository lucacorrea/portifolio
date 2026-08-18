<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();

/*
|--------------------------------------------------------------------------
| Ambiente e modelo solicitados
|--------------------------------------------------------------------------
*/

$requestedEnvironment = strtolower(
    trim((string) ($_POST['ambiente'] ?? 'homologacao'))
);

$requestedModel = trim(
    (string) ($_POST['modelo'] ?? '65')
);

$validEnvironments = [
    'homologacao',
    'producao',
];

$validModels = [
    '55',
    '65',
];

$productionRequest =
    $requestedEnvironment === 'producao';

$requiredPermission = $productionRequest
    ? 'nota_fiscal.ativar_producao'
    : 'nota_fiscal.testar_integracao';

[$application, $session] =
    os_action_context($requiredPermission);

/*
|--------------------------------------------------------------------------
| Fallback de retorno
|--------------------------------------------------------------------------
|
| Mesmo que return_to não esteja presente, o usuário volta para o mesmo
| ambiente e o mesmo modelo que estava testando.
|
*/

$safeEnvironmentForReturn =
    in_array($requestedEnvironment, $validEnvironments, true)
        ? $requestedEnvironment
        : 'homologacao';

$safeModelForReturn =
    in_array($requestedModel, $validModels, true)
        ? $requestedModel
        : '65';

$fallbackUrl =
    'configuracoes-fiscais.php'
    . '?ambiente='
    . rawurlencode($safeEnvironmentForReturn)
    . '&modelo='
    . rawurlencode($safeModelForReturn);

try {
    /*
    |--------------------------------------------------------------------------
    | Validação
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $requestedEnvironment,
            $validEnvironments,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Ambiente fiscal inválido. '
            . 'Selecione homologação ou produção.'
        );
    }

    if (
        !in_array(
            $requestedModel,
            $validModels,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Modelo fiscal inválido. '
            . 'Selecione NF-e 55 ou NFC-e 65.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Usuário
    |--------------------------------------------------------------------------
    */

    $user = $application
        ->authorization()
        ->requireLogin();

    /*
    |--------------------------------------------------------------------------
    | Configuração
    |--------------------------------------------------------------------------
    */

    $configurationId =
        os_posted_positive_int('configuracao_id');

    /*
    |--------------------------------------------------------------------------
    | Teste real da SEFAZ
    |--------------------------------------------------------------------------
    */

    $status = $application
        ->fiscalSefazConnection()
        ->test(
            $configurationId,
            (int) $user->id(),
            $productionRequest
        );

    if (!is_array($status)) {
        throw new RuntimeException(
            'O serviço fiscal retornou uma resposta inválida.'
        );
    }

    $code = trim(
        (string) ($status['code'] ?? '')
    );

    $message = trim(
        (string) ($status['message'] ?? '')
    );

    $serviceAvailable =
        (($status['success'] ?? false) === true)
        || $code === '107';

    if ($message === '') {
        $message = $serviceAvailable
            ? 'Serviço em operação'
            : 'A SEFAZ não retornou uma descrição.';
    }

    /*
    |--------------------------------------------------------------------------
    | Resultado
    |--------------------------------------------------------------------------
    */

    if (!$serviceAvailable) {
        $safeCode =
            $code !== ''
                ? $code
                : 'falha';

        $session->flash(
            'danger',
            'SEFAZ '
            . $requestedEnvironment
            . ' indisponível ou não validada ('
            . $safeCode
            . '): '
            . rtrim(
                $message,
                ". \t\n\r\0\x0B"
            )
            . '.'
        );

        os_redirect_back(
            $application,
            $fallbackUrl
        );
    }

    $session->flash(
        'success',
        'Comunicação com a SEFAZ '
        . $requestedEnvironment
        . ' validada com sucesso ('
        . ($code !== '' ? $code : '107')
        . '): '
        . rtrim(
            $message,
            ". \t\n\r\0\x0B"
        )
        . '.'
    );
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    try {
        $errorReference =
            date('Ymd-His')
            . '-'
            . bin2hex(random_bytes(4));
    } catch (Throwable $ignored) {
        $errorReference =
            date('Ymd-His');
    }

    error_log(
        sprintf(
            '[Fiscal SEFAZ][%s] ambiente=%s modelo=%s config=%s exception=%s message=%s file=%s line=%d',
            $errorReference,
            $requestedEnvironment,
            $requestedModel,
            isset($configurationId)
                ? (string) $configurationId
                : 'nao_informada',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    $session->flash(
        'danger',
        'Não foi possível completar o teste com a SEFAZ. '
        . 'Referência técnica: '
        . $errorReference
        . '. Consulte o log do servidor para identificar a causa.'
    );
}

os_redirect_back(
    $application,
    $fallbackUrl
);
