<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();

/*
|--------------------------------------------------------------------------
| Ambiente solicitado
|--------------------------------------------------------------------------
|
| Nunca devemos considerar silenciosamente qualquer valor inválido como
| homologação. Isso poderia esconder erro de formulário ou tentativa de
| manipulação da requisição.
|
*/
$requestedEnvironment = strtolower(
    trim((string) ($_POST['ambiente'] ?? 'homologacao'))
);

$validEnvironments = [
    'homologacao',
    'producao',
];

/*
|--------------------------------------------------------------------------
| Permissão
|--------------------------------------------------------------------------
|
| Produção exige explicitamente a permissão de ativação/produção.
| Homologação utiliza a permissão específica de teste.
|
| Para ambiente inválido ainda carregamos um contexto seguro de homologação,
| porém a requisição será interrompida antes de chamar qualquer serviço SEFAZ.
|
*/
$productionRequest = $requestedEnvironment === 'producao';

$requiredPermission = $productionRequest
    ? 'nota_fiscal.ativar_producao'
    : 'nota_fiscal.testar_integracao';

[$application, $session] = os_action_context($requiredPermission);

try {
    /*
    |--------------------------------------------------------------------------
    | Validação de ambiente
    |--------------------------------------------------------------------------
    */
    if (!in_array($requestedEnvironment, $validEnvironments, true)) {
        throw new InvalidArgumentException(
            'Ambiente fiscal inválido. Selecione homologação ou produção.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Usuário autenticado
    |--------------------------------------------------------------------------
    */
    $user = $application
        ->authorization()
        ->requireLogin();

    /*
    |--------------------------------------------------------------------------
    | Configuração fiscal
    |--------------------------------------------------------------------------
    */
    $configurationId = os_posted_positive_int('configuracao_id');

    /*
    |--------------------------------------------------------------------------
    | Teste real da SEFAZ
    |--------------------------------------------------------------------------
    |
    | O serviço fiscal deve:
    |
    | - carregar a configuração;
    | - conferir o ambiente;
    | - carregar o certificado A1;
    | - montar o Tools/NFePHP;
    | - realizar comunicação mTLS;
    | - chamar Status Serviço;
    | - persistir o resultado do teste;
    |
    */
    $status = $application
        ->fiscalSefazConnection()
        ->test(
            $configurationId,
            (int) $user->id(),
            $productionRequest
        );

    /*
    |--------------------------------------------------------------------------
    | Normalização da resposta
    |--------------------------------------------------------------------------
    */
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

    /*
     * O Status Serviço da NF-e/NFC-e utiliza cStat 107 para
     * "Serviço em Operação".
     *
     * Também respeitamos success=true caso a camada fiscal já normalize isso.
     */
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
    |
    | IMPORTANTE:
    | O código anterior sempre mostrava "SEFAZ disponível" caso test()
    | simplesmente retornasse um array, mesmo que esse array tivesse
    | code = erro_tecnico.
    |
    | Aqui isso foi corrigido.
    |
    */
    if (!$serviceAvailable) {
        $safeCode = $code !== ''
            ? $code
            : 'falha';

        $session->flash(
            'danger',
            'SEFAZ '
            . $requestedEnvironment
            . ' indisponível ou não validada ('
            . $safeCode
            . '): '
            . rtrim($message, ". \t\n\r\0\x0B")
            . '.'
        );

        os_redirect_back(
            $application,
            'configuracoes-fiscais.php'
        );
    }

    $session->flash(
        'success',
        'Comunicação com a SEFAZ '
        . $requestedEnvironment
        . ' validada com sucesso ('
        . ($code !== '' ? $code : '107')
        . '): '
        . rtrim($message, ". \t\n\r\0\x0B")
        . '.'
    );
} catch (InvalidArgumentException $exception) {
    /*
    |--------------------------------------------------------------------------
    | Erros de validação
    |--------------------------------------------------------------------------
    */
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    /*
    |--------------------------------------------------------------------------
    | Erro técnico
    |--------------------------------------------------------------------------
    |
    | O usuário recebe uma referência.
    | O log recebe o erro completo.
    |
    | Não exibimos stack trace, caminho do certificado, senha ou outras
    | informações sensíveis no navegador.
    |
    */
    try {
        $errorReference =
            date('Ymd-His')
            . '-'
            . bin2hex(random_bytes(4));
    } catch (Throwable $ignored) {
        $errorReference = date('Ymd-His');
    }

    error_log(
        sprintf(
            '[Fiscal SEFAZ][%s] ambiente=%s config=%s exception=%s message=%s file=%s line=%d',
            $errorReference,
            $requestedEnvironment,
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
    'configuracoes-fiscais.php'
);