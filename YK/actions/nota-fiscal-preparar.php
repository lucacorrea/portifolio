<?php

declare(strict_types=1);

use App\Fiscal\Service\FiscalSafeLogger;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

require __DIR__ . '/os-action-common.php';

os_require_post_request();

/*
|--------------------------------------------------------------------------
| Contexto da ação
|--------------------------------------------------------------------------
*/

[$application, $session] = os_action_context(
    'nota_fiscal.emitir'
);

/*
|--------------------------------------------------------------------------
| Variáveis de diagnóstico
|--------------------------------------------------------------------------
|
| Servem apenas para sabermos em qual etapa ocorreu uma eventual falha.
|
*/

$stage = 'inicio';

$orderId = 0;
$documentId = 0;
$model = '';
$environment = 'homologacao';

/*
|--------------------------------------------------------------------------
| Sanitização de mensagem técnica
|--------------------------------------------------------------------------
|
| Em homologação queremos enxergar a causa real do erro.
|
| Porém:
| - não exibimos stack trace;
| - não exibimos senha;
| - não exibimos token;
| - não exibimos chave privada;
| - não exibimos caminhos absolutos do servidor.
|
*/

if (!function_exists('fiscal_action_safe_diagnostic')) {
    function fiscal_action_safe_diagnostic(
        Throwable $exception
    ): string {
        $message = trim(
            $exception->getMessage()
        );

        if ($message === '') {
            return 'Erro interno sem mensagem técnica.';
        }

        /*
         * Remove tags HTML.
         */
        $message = strip_tags(
            $message
        );

        /*
         * Remove caracteres de controle.
         */
        $message = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            $message
        ) ?? '';

        /*
         * Protege caminhos Linux comuns.
         *
         * Ex:
         * /home/usuario/domains/...
         */
        $message = preg_replace(
            '#/(?:home|var|srv|opt|usr|etc)/[^\s\'"]+#i',
            '[caminho protegido]',
            $message
        ) ?? $message;

        /*
         * Protege caminhos Windows.
         */
        $message = preg_replace(
            '#[A-Z]:\\\\[^\s\'"]+#i',
            '[caminho protegido]',
            $message
        ) ?? $message;

        /*
         * Protege valores que possam parecer segredo.
         */
        $message = preg_replace(
            '/\b('
            . 'senha'
            . '|password'
            . '|secret'
            . '|token'
            . '|api[_\-\s]?key'
            . '|master[_\-\s]?key'
            . ')\b\s*[:=]\s*[^\s,;]+/iu',
            '$1=[protegido]',
            $message
        ) ?? $message;

        /*
         * Protege bloco PEM caso alguma biblioteca
         * coloque certificado/chave na mensagem.
         */
        $message = preg_replace(
            '/-----BEGIN[^-]+-----.*?-----END[^-]+-----/su',
            '[certificado protegido]',
            $message
        ) ?? $message;

        /*
         * Normaliza espaços.
         */
        $message = preg_replace(
            '/\s+/u',
            ' ',
            $message
        ) ?? '';

        $message = trim(
            $message
        );

        /*
         * Limita o tamanho mostrado na interface.
         */
        if (function_exists('mb_substr')) {
            return mb_substr(
                $message,
                0,
                500,
                'UTF-8'
            );
        }

        return substr(
            $message,
            0,
            500
        );
    }
}

/*
|--------------------------------------------------------------------------
| Nome amigável da etapa
|--------------------------------------------------------------------------
*/

if (!function_exists('fiscal_action_stage_label')) {
    function fiscal_action_stage_label(
        string $stage
    ): string {
        return match ($stage) {
            'autenticacao' =>
                'autenticação',

            'validacao_requisicao' =>
                'validação da solicitação',

            'preparacao_documento' =>
                'preparação do documento fiscal',

            'transmissao_documento' =>
                'geração, assinatura ou transmissão do documento',

            'tratamento_resposta' =>
                'processamento da resposta fiscal',

            default =>
                'processamento fiscal',
        };
    }
}

try {
    /*
    |--------------------------------------------------------------------------
    | 1. Autenticação
    |--------------------------------------------------------------------------
    */

    $stage = 'autenticacao';

    $user = $application
        ->authorization()
        ->requireLogin();

    $userId = (int) $user->id();

    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'Usuário inválido para emissão fiscal.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Dados da requisição
    |--------------------------------------------------------------------------
    */

    $stage = 'validacao_requisicao';

    $orderId = os_posted_positive_int(
        'ordem_servico_id'
    );

    $model = trim(
        (string) (
            $_POST['modelo']
            ?? ''
        )
    );

    $environment = strtolower(
        trim(
            (string) (
                $_POST['ambiente']
                ?? 'homologacao'
            )
        )
    );

    $idempotencyKey = strtolower(
        trim(
            (string) (
                $_POST['idempotency_key']
                ?? ''
            )
        )
    );

    /*
    |--------------------------------------------------------------------------
    | Validação do modelo
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $model,
            ['55', '65'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Selecione um modelo fiscal válido: '
            . 'NF-e (55) ou NFC-e (65).'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validação do ambiente
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $environment,
            [
                'homologacao',
                'producao',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Ambiente fiscal inválido.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validação do token de idempotência
    |--------------------------------------------------------------------------
    |
    | Esse token impede emissão duplicada quando houver:
    |
    | - duplo clique;
    | - reload;
    | - timeout;
    | - repetição acidental da requisição.
    |
    */

    if (
        preg_match(
            '/^[a-f0-9]{64}$/',
            $idempotencyKey
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'Token de emissão fiscal inválido. '
            . 'Atualize a página e tente novamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Preparação
    |--------------------------------------------------------------------------
    */

    $stage = 'preparacao_documento';

    $prepared = $application
        ->fiscalDocuments()
        ->prepareFromServiceOrder(
            $orderId,
            $model,
            $environment,
            $idempotencyKey,
            $userId
        );

    if (!is_array($prepared)) {
        throw new RuntimeException(
            'O serviço de preparação fiscal retornou '
            . 'um resultado inválido.'
        );
    }

    $documentId = (int) (
        $prepared['id']
        ?? 0
    );

    if ($documentId <= 0) {
        throw new RuntimeException(
            'O documento fiscal foi preparado, '
            . 'mas nenhum ID válido foi retornado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Transmissão
    |--------------------------------------------------------------------------
    |
    | O próprio FiscalAuthorizationService deve proteger documentos que
    | estejam como processando ou pendente_reconsulta para não retransmitir
    | uma mesma NF-e/NFC-e indevidamente.
    |
    */

    $stage = 'transmissao_documento';

    $result = $application
        ->fiscalAuthorization()
        ->transmit(
            $documentId,
            $userId
        );

    if (!is_array($result)) {
        throw new RuntimeException(
            'O serviço de autorização fiscal retornou '
            . 'uma resposta interna inválida.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Resposta
    |--------------------------------------------------------------------------
    */

    $stage = 'tratamento_resposta';

    $status = strtolower(
        trim(
            (string) (
                $result['status']
                ?? ''
            )
        )
    );

    $cstat = trim(
        (string) (
            $result['cstat']
            ?? ''
        )
    );

    $reason = trim(
        (string) (
            $result['reason']
            ?? ''
        )
    );

    /*
    |--------------------------------------------------------------------------
    | Documento autorizado
    |--------------------------------------------------------------------------
    */

    if ($status === 'autorizado') {
        $message =
            $model === '55'
                ? 'NF-e autorizada pela SEFAZ'
                : 'NFC-e autorizada pela SEFAZ';

        if ($cstat !== '') {
            $message .=
                ' ('
                . $cstat
                . ')';
        }

        $message .=
            '. A impressão válida foi liberada.';

        $session->flash(
            'success',
            $message
        );

        os_redirect_back(
            $application,
            'faturamento.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reconsulta obrigatória
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    | não tente retransmitir automaticamente.
    |
    */

    if ($status === 'pendente_reconsulta') {
        $session->flash(
            'warning',
            $reason !== ''
                ? $reason
                : (
                    'A transmissão ficou pendente. '
                    . 'Reconsulte a situação na SEFAZ '
                    . 'antes de tentar uma nova emissão.'
                )
        );

        os_redirect_back(
            $application,
            'faturamento.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Documento rejeitado
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $status,
            [
                'rejeitado',
                'denegado',
                'erro',
            ],
            true
        )
    ) {
        $message =
            'SEFAZ '
            . (
                $cstat !== ''
                    ? $cstat
                    : '-'
            )
            . ': '
            . (
                $reason !== ''
                    ? $reason
                    : 'Documento fiscal não autorizado.'
            );

        $session->flash(
            'danger',
            $message
        );

        os_redirect_back(
            $application,
            'faturamento.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status interno desconhecido
    |--------------------------------------------------------------------------
    */

    throw new RuntimeException(
        'O fluxo fiscal retornou um status interno '
        . 'não reconhecido: '
        . (
            $status !== ''
                ? $status
                : '[vazio]'
        )
        . '.'
    );

} catch (InvalidArgumentException $exception) {
    /*
    |--------------------------------------------------------------------------
    | Erros seguros de validação
    |--------------------------------------------------------------------------
    |
    | Esses erros foram criados pela própria aplicação e podem ser
    | apresentados ao usuário.
    |
    */

    $session->flash(
        'danger',
        $exception->getMessage()
    );

} catch (Throwable $exception) {
    /*
    |--------------------------------------------------------------------------
    | Erro técnico inesperado
    |--------------------------------------------------------------------------
    */

    $correlationId = FiscalSafeLogger::record(
        $exception,
        'prepare_and_transmit_' . $stage
    );

    /*
     * Registro adicional sem dados pessoais.
     *
     * Não gravamos:
     * - CPF;
     * - CNPJ;
     * - endereço;
     * - senha;
     * - certificado;
     * - XML.
     */
    error_log(
        sprintf(
            '[FiscalEmission] ref=%s stage=%s order=%d document=%d model=%s environment=%s exception=%s',
            $correlationId,
            $stage,
            $orderId,
            $documentId,
            $model !== '' ? $model : '-',
            $environment !== '' ? $environment : '-',
            get_class($exception)
        )
    );

    $stageLabel = fiscal_action_stage_label(
        $stage
    );

    /*
    |--------------------------------------------------------------------------
    | HOMOLOGAÇÃO
    |--------------------------------------------------------------------------
    |
    | Durante homologação mostramos uma versão sanitizada do erro.
    |
    | Isso é importante agora, porque precisamos descobrir se o problema
    | está em:
    |
    | - snapshot;
    | - banco;
    | - XML;
    | - NFePHP;
    | - assinatura;
    | - certificado;
    | - storage;
    | - comunicação SEFAZ.
    |
    */

    if ($environment === 'homologacao') {
        $diagnostic =
            fiscal_action_safe_diagnostic(
                $exception
            );

        $session->flash(
            'danger',
            'Falha técnica durante '
            . $stageLabel
            . '. '
            . 'Detalhe: '
            . $diagnostic
            . ' '
            . 'Referência: '
            . $correlationId
            . '.'
        );
    } else {
        /*
        |--------------------------------------------------------------------------
        | PRODUÇÃO
        |--------------------------------------------------------------------------
        |
        | Produção nunca recebe detalhes internos.
        |
        */

        $session->flash(
            'danger',
            'Não foi possível concluir a emissão fiscal '
            . 'durante '
            . $stageLabel
            . '. '
            . 'Referência: '
            . $correlationId
            . '.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Retorno
|--------------------------------------------------------------------------
*/

os_redirect_back(
    $application,
    'faturamento.php'
);