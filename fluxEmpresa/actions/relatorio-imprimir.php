<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Company\DTO\CompanyScope;
use App\Core\Application;
use App\Report\Repository\ServiceExecutionPrintRepository;
use App\Report\Service\ServiceExecutionPrintService;

/*
 * =========================================================
 * MÉTODO HTTP
 * =========================================================
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

/*
 * =========================================================
 * CABEÇALHOS DE SEGURANÇA
 * =========================================================
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

/*
 * =========================================================
 * BOOTSTRAP DA APLICAÇÃO
 * =========================================================
 */
$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var Application $application */
$application = $app['application'];

$session = $application->session();
$session->start();

$companyId = null;
$periodForLog = [];

try {
    /*
     * =====================================================
     * AUTENTICAÇÃO
     * =====================================================
     */
    $authorization = $application->authorization();
    $currentUser = $authorization->requireLogin();

    /*
     * A empresa é obtida exclusivamente pelo contexto
     * autenticado. Não aceitamos empresa_id pela URL.
     */
    $companyScope = $application
        ->operationalCompanyContext()
        ->resolve($currentUser);

    $companyId = $companyScope->id();

    /*
     * =====================================================
     * PERMISSÃO
     * =====================================================
     */
    report_print_require_any_permission(
        $companyScope,
        [
            'relatorio.operacional',
            'relatorio.produtividade',
            'relatorio.financeiro',
        ]
    );

    /*
     * =====================================================
     * PERÍODO
     * =====================================================
     *
     * Reutiliza a validação do ProductionReportService:
     *
     * - mês ou período personalizado;
     * - data inicial inclusiva;
     * - data final transformada em fim exclusivo;
     * - limite máximo permitido pelo service;
     * - datas normalizadas.
     */
    $periodForLog = $application
        ->reports()
        ->resolvePeriod($_GET);

    /*
     * Usuários sem a permissão financeira continuam podendo
     * imprimir os serviços, mas não recebem valores.
     */
    $canViewFinancial = $companyScope->allows(
        'relatorio.financeiro'
    );

    /*
     * =====================================================
     * RELATÓRIO DETALHADO
     * =====================================================
     */
    $repository = new ServiceExecutionPrintRepository(
        $application
            ->database()
            ->connection(),
        $companyScope
    );

    $printService = new ServiceExecutionPrintService(
        $repository
    );

    $reportData = $printService->report(
        $periodForLog,
        $_GET,
        $canViewFinancial
    );

    /*
     * =====================================================
     * SEÇÃO DE ORIGEM
     * =====================================================
     *
     * Utilizada somente para montar o botão de retorno.
     */
    $sourceSection = strtolower(
        trim(
            (string) (
                $_GET['source_section']
                ?? 'clientes'
            )
        )
    );

    if (
        !in_array(
            $sourceSection,
            [
                'empresa',
                'clientes',
                'servicos',
                'equipe',
            ],
            true
        )
    ) {
        $sourceSection = 'clientes';
    }

    /*
     * =====================================================
     * URL DE RETORNO
     * =====================================================
     */
    $returnQuery = is_array(
        $periodForLog['query'] ?? null
    )
        ? $periodForLog['query']
        : [];

    $returnQuery['secao'] = $sourceSection;

    $returnUrl = $application
        ->redirect()
        ->applicationUrl(
            'relatorios.php?'
            . http_build_query(
                $returnQuery,
                '',
                '&',
                PHP_QUERY_RFC3986
            )
        );

    /*
     * =====================================================
     * INFORMAÇÕES DO CABEÇALHO
     * =====================================================
     */
    $companyName = trim(
        $companyScope->name()
    );

    if ($companyName === '') {
        $companyName = 'Empresa não informada';
    }

    $generatedAt = date('d/m/Y H:i:s');

    /*
     * Helpers visuais, incluindo h().
     */
    require_once dirname(__DIR__)
        . '/includes/ui.php';

    /*
     * =====================================================
     * VIEW DA IMPRESSÃO
     * =====================================================
     */
    require dirname(__DIR__)
        . '/pages/relatorios/impressao-servicos.php';

    exit;
} catch (AuthenticationException $exception) {
    /*
     * =====================================================
     * SESSÃO EXPIRADA
     * =====================================================
     */
    $session->flash(
        'warning',
        'Sua sessão expirou. Entre novamente.'
    );

    header(
        'Location: '
        . $application
            ->redirect()
            ->loginUrl(),
        true,
        303
    );

    exit;
} catch (AuthorizationException $exception) {
    /*
     * =====================================================
     * ACESSO NEGADO
     * =====================================================
     */
    report_print_error_page(
        403,
        'Acesso negado',
        'Você não possui permissão para imprimir este relatório.'
    );
} catch (InvalidArgumentException $exception) {
    /*
     * =====================================================
     * FILTROS INVÁLIDOS
     * =====================================================
     */
    report_print_error_page(
        422,
        'Filtros inválidos',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    /*
     * =====================================================
     * ERRO INTERNO
     * =====================================================
     */
    report_print_log_failure(
        $exception,
        $companyId,
        $periodForLog
    );

    report_print_error_page(
        500,
        'Não foi possível gerar o relatório',
        'Ocorreu uma falha ao preparar os dados para impressão.'
    );
}

/**
 * Exige pelo menos uma das permissões informadas.
 *
 * @param string[] $permissions
 */
function report_print_require_any_permission(
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
 * Exibe uma página de erro simples e segura.
 */
function report_print_error_page(
    int $status,
    string $title,
    string $message
): never {
    http_response_code($status);

    $safeTitle = htmlspecialchars(
        $title,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeMessage = htmlspecialchars(
        $message,
        ENT_QUOTES,
        'UTF-8'
    );

    echo '<!DOCTYPE html>';
    echo '<html lang="pt-BR">';

    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $safeTitle . '</title>';

    echo '<style>';
    echo '*{box-sizing:border-box;}';
    echo 'body{margin:0;padding:24px;background:#f1f5f9;color:#0f172a;font-family:Arial,sans-serif;}';
    echo '.error-box{max-width:680px;margin:60px auto;padding:28px;background:#fff;border:1px solid #cbd5e1;border-radius:14px;box-shadow:0 12px 30px rgba(15,23,42,.08);}';
    echo '.error-icon{display:grid;width:52px;height:52px;margin-bottom:18px;place-items:center;border-radius:50%;background:#fee2e2;color:#b91c1c;font-size:24px;font-weight:700;}';
    echo 'h1{margin:0 0 10px;font-size:24px;}';
    echo 'p{margin:0;color:#475569;line-height:1.6;}';
    echo '.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px;}';
    echo '.button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 16px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;text-decoration:none;font-weight:700;cursor:pointer;}';
    echo '.button-primary{border-color:#1f4e78;background:#1f4e78;color:#fff;}';
    echo '</style>';

    echo '</head>';

    echo '<body>';

    echo '<main class="error-box">';
    echo '<div class="error-icon">!</div>';
    echo '<h1>' . $safeTitle . '</h1>';
    echo '<p>' . $safeMessage . '</p>';

    echo '<div class="actions">';

    echo '<button'
        . ' class="button button-primary"'
        . ' type="button"'
        . ' onclick="history.back()"'
        . '>'
        . 'Voltar'
        . '</button>';

    echo '<a'
        . ' class="button"'
        . ' href="../relatorios.php"'
        . '>'
        . 'Abrir relatórios'
        . '</a>';

    echo '</div>';
    echo '</main>';

    echo '</body>';
    echo '</html>';

    exit;
}

/**
 * Registra somente informações técnicas mínimas.
 *
 * Não registra:
 *
 * - SQL;
 * - stack trace;
 * - cookies;
 * - sessão;
 * - documentos;
 * - telefones;
 * - dados pessoais;
 * - conteúdo completo dos filtros.
 *
 * @param array<string,mixed> $period
 */
function report_print_log_failure(
    Throwable $exception,
    ?int $companyId,
    array $period
): void {
    $entry = [
        'timestamp' => date('c'),

        'event' => 'service_execution_print_failed',

        'company_id' => $companyId,

        'period_start' => isset($period['start'])
            ? (string) $period['start']
            : null,

        'period_end_exclusive' => isset(
            $period['end_exclusive']
        )
            ? (string) $period['end_exclusive']
            : null,

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

    error_log(
        is_string($encoded)
            ? $encoded
            : 'service_execution_print_failed'
    );
}