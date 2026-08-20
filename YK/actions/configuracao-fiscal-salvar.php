<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();

/*
 * Normalização e validação antecipada.
 *
 * Criar uma versão de configuração, inclusive para produção,
 * exige nota_fiscal.configurar.
 *
 * ATIVAR a produção continua sendo outra operação, protegida por
 * actions/configuracao-fiscal-ativar.php e pela permissão
 * nota_fiscal.ativar_producao.
 */
$requestedEnvironment = strtolower(
    trim(
        (string) (
            $_POST['ambiente']
            ?? 'homologacao'
        )
    )
);

$requestedModel = trim(
    (string) (
        $_POST['modelo']
        ?? '65'
    )
);

if (
    !in_array(
        $requestedEnvironment,
        ['homologacao', 'producao'],
        true
    )
) {
    $requestedEnvironment = 'homologacao';
}

if (
    !in_array(
        $requestedModel,
        ['55', '65'],
        true
    )
) {
    $requestedModel = '65';
}

/*
 * IMPORTANTE:
 * Não usar nota_fiscal.ativar_producao para SALVAR configuração.
 *
 * Antes, quando ambiente=producao, a action exigia ativar_producao,
 * fazendo o formulário falhar mesmo para quem tinha
 * nota_fiscal.configurar.
 */
[$application, $session] = os_action_context(
    'nota_fiscal.configurar'
);

$fallback =
    'configuracoes-fiscais.php?'
    . http_build_query(
        [
            'ambiente' => $requestedEnvironment,
            'modelo' => $requestedModel,
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );

try {
    $user =
        $application
            ->authorization()
            ->requireLogin();

    /*
     * Repassa ambiente/modelo normalizados.
     */
    $payload = $_POST;
    $payload['ambiente'] = $requestedEnvironment;
    $payload['modelo'] = $requestedModel;

    $application
        ->fiscalConfiguration()
        ->createConfiguration(
            $payload,
            (int) $user->id()
        );

    $documentLabel =
        $requestedModel === '55'
            ? 'NF-e modelo 55'
            : 'NFC-e modelo 65';

    $environmentLabel =
        $requestedEnvironment === 'producao'
            ? 'produção'
            : 'homologação';

    $session->flash(
        'success',
        'Nova versão da configuração de '
        . $documentLabel
        . ' em '
        . $environmentLabel
        . ' criada com sucesso.'
    );
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'Fiscal configuration save failed ['
        . get_class($exception)
        . ']: '
        . $exception->getMessage()
    );

    $session->flash(
        'danger',
        'Não foi possível salvar a configuração fiscal.'
    );
}

/*
 * Mantém o usuário exatamente no modelo/ambiente que estava editando.
 */
os_redirect_back(
    $application,
    $fallback
);
