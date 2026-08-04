<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Company\DTO\CompanyScope;
use App\Core\Application;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Vary: X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');

    report_action_json_response(
        405,
        [
            'success' => false,
            'message' => 'Método não permitido.',
        ]
    );
}

$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var Application $application */
$application = $app['application'];

$session = $application->session();
$session->start();

$section = strtolower(
    trim((string) ($_GET['secao'] ?? ''))
);

$action = strtolower(
    trim((string) ($_GET['acao'] ?? 'lista'))
);

$companyId = null;
$periodForLog = [];

try {
    $authorization = $application->authorization();
    $authorization->requireLogin();

    /*
     * A empresa nunca é recebida pela URL.
     * O contexto é resolvido exclusivamente pelo backend.
     */
    $companyScope = $application->companyScope();
    $companyId = $companyScope->id();

    if (
        !in_array(
            $section,
            [
                'clientes',
                'servicos',
                'equipe',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Seção de relatório inválida.'
        );
    }

    if (
        !in_array(
            $action,
            [
                'lista',
                'detalhes',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Ação de relatório inválida.'
        );
    }

    if (
        $section === 'equipe'
        && $action !== 'lista'
    ) {
        throw new InvalidArgumentException(
            'Ação indisponível para esta seção.'
        );
    }

    $service = $application->reports();

    /*
     * Validação antecipada para registrar somente o período
     * seguro e normalizado em caso de erro técnico.
     */
    $periodForLog = $service->resolvePeriod($_GET);

    $canViewFinancial = $companyScope->allows(
        'relatorio.financeiro'
    );

    $canViewCommission = $companyScope->allows(
        'relatorio.comissao.visualizar'
    );

    $canConfigureGoal = $companyScope->allows(
        'relatorio.meta_comissao.configurar'
    );

    $canOpenOrders = $companyScope->allows(
        'os.visualizar'
    );

    $reportView = 'list';

    /*
     * =====================================================
     * RELATÓRIO POR CLIENTE
     * =====================================================
     */
    if ($section === 'clientes') {
        report_action_require_any_permission(
            $companyScope,
            [
                'relatorio.operacional',
                'relatorio.financeiro',
            ]
        );

        if ($action === 'detalhes') {
            $reportView = 'details';

            $reportData = $service->clientOrderDetailsReport(
                $_GET,
                $canViewFinancial
            );
        } else {
            $reportData = $service->clientReport(
                $_GET,
                $canViewFinancial
            );
        }

        $partial = dirname(__DIR__)
            . '/pages/relatorios/clientes.php';
    }

    /*
     * =====================================================
     * RELATÓRIO POR SERVIÇO
     * =====================================================
     */
    elseif ($section === 'servicos') {
        report_action_require_any_permission(
            $companyScope,
            [
                'relatorio.operacional',
                'relatorio.produtividade',
            ]
        );

        if ($action === 'detalhes') {
            $reportView = 'details';

            $reportData = $service
                ->serviceExecutionDetailsReport(
                    $_GET,
                    $canViewFinancial
                );
        } else {
            $reportData = $service->serviceReport(
                $_GET,
                $canViewFinancial
            );
        }

        $partial = dirname(__DIR__)
            . '/pages/relatorios/servicos.php';
    }

    /*
     * =====================================================
     * EQUIPE, META E COMISSÃO
     * =====================================================
     */
    else {
        /*
         * A permissão de comissão foi mantida aqui para
         * preservar o comportamento do relatório existente,
         * no qual quem visualiza comissão também acessa
         * a apuração dos funcionários.
         */
        report_action_require_any_permission(
            $companyScope,
            [
                'relatorio.funcionarios',
                'relatorio.produtividade',
                'relatorio.comissao.visualizar',
            ]
        );

        $reportData = $service->teamReport(
            $_GET,
            $canViewCommission
        );

        $partial = dirname(__DIR__)
            . '/pages/relatorios/equipe.php';
    }

    if (!is_file($partial)) {
        throw new RuntimeException(
            'Partial do relatório não encontrada.'
        );
    }

    require_once dirname(__DIR__) . '/includes/ui.php';

    /*
     * Gera uma URL interna segura para abrir a OS.
     *
     * A URL não é recebida pelo navegador. Somente o número
     * da OS retornado pelo repository é utilizado na busca.
     */
    $reportOrderUrl = static function (
        array $row
    ) use (
        $application,
        $canOpenOrders
    ): ?string {
        if (!$canOpenOrders) {
            return null;
        }

        $orderNumber = trim(
            (string) ($row['order_number'] ?? '')
        );

        if ($orderNumber === '') {
            return null;
        }

        return $application
            ->redirect()
            ->applicationUrl(
                'ordens-servico.php?search='
                . rawurlencode($orderNumber)
            );
    };

    $html = report_action_render_partial(
        $partial,
        [
            'application' => $application,
            'companyScope' => $companyScope,
            'reportData' => $reportData,
            'reportSection' => $section,
            'reportView' => $reportView,
            'reportCanViewFinancial' => $canViewFinancial,
            'reportCanViewCommission' => $canViewCommission,
            'reportCanConfigureGoal' => $canConfigureGoal,
            'reportCanOpenOrders' => $canOpenOrders,
            'reportOrderUrl' => $reportOrderUrl,
        ]
    );

    report_action_json_response(
        200,
        [
            'success' => true,
            'section' => $section,
            'action' => $action,
            'html' => $html,
            'period' => $reportData['period'] ?? null,
            'pagination' => $reportData['pagination'] ?? null,
        ]
    );
} catch (AuthenticationException $exception) {
    report_action_json_response(
        401,
        [
            'success' => false,
            'message' => 'Sua sessão expirou. Entre novamente.',
        ]
    );
} catch (AuthorizationException $exception) {
    report_action_json_response(
        403,
        [
            'success' => false,
            'message' => 'Você não possui permissão para visualizar esta seção.',
        ]
    );
} catch (InvalidArgumentException $exception) {
    /*
     * As mensagens do service são controladas e não contêm
     * SQL ou informações internas do banco.
     */
    report_action_json_response(
        422,
        [
            'success' => false,
            'message' => $exception->getMessage(),
        ]
    );
} catch (Throwable $exception) {
    report_action_log_failure(
        $exception,
        $section,
        $companyId,
        $periodForLog
    );

    report_action_json_response(
        500,
        [
            'success' => false,
            'message' => 'Não foi possível carregar esta seção do relatório.',
        ]
    );
}

/**
 * Encerra a requisição com uma resposta JSON.
 *
 * @param array<string,mixed> $payload
 */
function report_action_json_response(
    int $status,
    array $payload
): never {
    http_response_code($status);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if (!is_string($json)) {
        http_response_code(500);

        echo json_encode(
            [
                'success' => false,
                'message' => 'Não foi possível concluir a resposta.',
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    echo $json;
    exit;
}

/**
 * Exige pelo menos uma das permissões informadas.
 *
 * @param string[] $permissions
 */
function report_action_require_any_permission(
    CompanyScope $companyScope,
    array $permissions
): void {
    foreach ($permissions as $permission) {
        if ($companyScope->allows($permission)) {
            return;
        }
    }

    throw new AuthorizationException(
        'Acesso negado.'
    );
}

/**
 * Renderiza uma partial PHP isoladamente.
 *
 * @param array<string,mixed> $variables
 */
function report_action_render_partial(
    string $partial,
    array $variables
): string {
    extract(
        $variables,
        EXTR_SKIP
    );

    $previousBufferLevel = ob_get_level();

    ob_start();

    try {
        require $partial;

        $html = ob_get_clean();
    } catch (Throwable $exception) {
        while (
            ob_get_level() > $previousBufferLevel
        ) {
            ob_end_clean();
        }

        throw $exception;
    }

    if (!is_string($html)) {
        throw new RuntimeException(
            'Não foi possível renderizar a seção do relatório.'
        );
    }

    return $html;
}

/**
 * Registra somente o contexto técnico mínimo.
 *
 * Não registra:
 *
 * - SQL;
 * - stack trace;
 * - cookies;
 * - sessão;
 * - token CSRF;
 * - payload completo;
 * - documentos;
 * - telefones;
 * - dados pessoais de clientes.
 *
 * @param array<string,mixed> $period
 */
function report_action_log_failure(
    Throwable $exception,
    string $section,
    ?int $companyId,
    array $period
): void {
    $logDirectory = dirname(__DIR__)
        . '/storage/logs';

    $logFile = $logDirectory
        . '/app.log';

    $entry = [
        'timestamp' => date('c'),
        'event' => 'report_section_load_failed',
        'section' => $section !== ''
            ? $section
            : 'unknown',
        'company_id' => $companyId,
        'period' => [
            'start' => isset($period['start'])
                ? (string) $period['start']
                : null,

            'end_exclusive' => isset(
                $period['end_exclusive']
            )
                ? (string) $period['end_exclusive']
                : null,
        ],
        'exception_class' => get_class(
            $exception
        ),
        'exception_code' => (string) $exception
            ->getCode(),
    ];

    $encoded = json_encode(
        $entry,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if (!is_string($encoded)) {
        error_log(
            'Report section load failed: '
            . 'unable to encode safe log entry.'
        );

        return;
    }

    if (!is_dir($logDirectory)) {
        @mkdir(
            $logDirectory,
            0775,
            true
        );
    }

    $written = false;

    if (is_dir($logDirectory)) {
        $written = @file_put_contents(
            $logFile,
            $encoded . PHP_EOL,
            FILE_APPEND | LOCK_EX
        ) !== false;
    }

    if (!$written) {
        /*
         * Fallback seguro caso a hospedagem não permita
         * escrever no diretório storage/logs.
         */
        error_log($encoded);
    }
}