<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Company\DTO\CompanyScope;
use App\Core\Application;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();

$section = strtolower(trim((string) ($_GET['secao'] ?? '')));
$companyId = null;
$periodForLog = [];

try {
    $authorization = $application->authorization();
    $authorization->requireLogin();

    $companyScope = $application->companyScope();
    $companyId = $companyScope->id();

    if (!in_array($section, ['empresa', 'clientes', 'servicos', 'equipe'], true)) {
        throw new InvalidArgumentException('Seção de exportação inválida.');
    }

    report_export_require_permission($companyScope, $section);

    $service = $application->reports();
    $periodForLog = $service->resolvePeriod($_GET);

    $canViewFinancial = $companyScope->allows('relatorio.financeiro');
    $canViewCommission = $companyScope->allows('relatorio.comissao.visualizar');

    $input = $_GET;
    $input['per_page'] = 100;
    $input['page'] = 1;

    /*
     * A primeira consulta é executada antes do envio dos cabeçalhos.
     * Assim erros de validação ou banco não produzem um CSV vazio.
     */
    $firstReport = match ($section) {
        'empresa' => $service->companyReport($input, $canViewFinancial),
        'clientes' => $service->clientReport($input, $canViewFinancial),
        'servicos' => $service->serviceReport($input, $canViewFinancial),
        'equipe' => $service->teamReport($input, $canViewCommission),
    };

    $period = is_array($firstReport['period'] ?? null)
        ? $firstReport['period']
        : $periodForLog;

    report_export_send_headers(
        report_export_filename($section, $period)
    );

    $output = fopen('php://output', 'wb');

    if ($output === false) {
        throw new RuntimeException('Não foi possível iniciar a exportação.');
    }

    fwrite($output, "\xEF\xBB\xBF");

    report_export_metadata(
        $output,
        $section,
        $period
    );

    match ($section) {
        'empresa' => report_export_company(
            $output,
            $firstReport,
            $canViewFinancial
        ),
        'clientes' => report_export_clients(
            $output,
            $service,
            $input,
            $firstReport,
            $canViewFinancial
        ),
        'servicos' => report_export_services(
            $output,
            $service,
            $input,
            $firstReport,
            $canViewFinancial
        ),
        'equipe' => report_export_team(
            $output,
            $firstReport,
            $canViewCommission
        ),
    };

    fclose($output);
    exit;
} catch (AuthenticationException $exception) {
    $session->flash('warning', 'Sua sessão expirou. Entre novamente.');

    header(
        'Location: '
        . $application->redirect()->loginUrl(),
        true,
        303
    );
    exit;
} catch (AuthorizationException $exception) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Você não possui permissão para exportar este relatório.';
    exit;
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $exception->getMessage();
    exit;
} catch (Throwable $exception) {
    report_export_log_failure(
        $exception,
        $section,
        $companyId,
        $periodForLog
    );

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não foi possível gerar a exportação do relatório.';
    }

    exit;
}

function report_export_require_permission(
    CompanyScope $companyScope,
    string $section
): void {
    $permissions = match ($section) {
        'empresa' => [
            'relatorio.operacional',
            'relatorio.produtividade',
            'relatorio.financeiro',
        ],
        'clientes' => [
            'relatorio.operacional',
            'relatorio.financeiro',
        ],
        'servicos' => [
            'relatorio.operacional',
            'relatorio.produtividade',
        ],
        'equipe' => [
            'relatorio.funcionarios',
            'relatorio.produtividade',
            'relatorio.comissao.visualizar',
        ],
        default => [],
    };

    foreach ($permissions as $permission) {
        if ($companyScope->allows($permission)) {
            return;
        }
    }

    throw new AuthorizationException('Acesso negado.');
}

/** @param array<string,mixed> $period */
function report_export_filename(
    string $section,
    array $period
): string {
    $sectionName = match ($section) {
        'empresa' => 'visao-geral',
        'clientes' => 'clientes',
        'servicos' => 'servicos',
        'equipe' => 'equipe-metas-comissao',
        default => 'relatorio',
    };

    $start = preg_replace(
        '/[^0-9]/',
        '',
        (string) ($period['display_start'] ?? '')
    ) ?: date('Ymd');

    $end = preg_replace(
        '/[^0-9]/',
        '',
        (string) ($period['display_end'] ?? '')
    ) ?: $start;

    return sprintf(
        'relatorio-%s-%s-a-%s.csv',
        $sectionName,
        $start,
        $end
    );
}

function report_export_send_headers(string $filename): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header(
        'Content-Disposition: attachment; filename="'
        . str_replace('"', '', $filename)
        . '"'
    );
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
}

/**
 * @param resource $output
 * @param array<string,mixed> $period
 */
function report_export_metadata(
    $output,
    string $section,
    array $period
): void {
    $title = match ($section) {
        'empresa' => 'Visão geral da empresa',
        'clientes' => 'Relatório por cliente',
        'servicos' => 'Relatório por serviço',
        'equipe' => 'Equipe, metas e comissão',
        default => 'Relatório',
    };

    report_export_row($output, ['Relatório', $title]);

    report_export_row(
        $output,
        ['Período', (string) ($period['label'] ?? '—')]
    );

    report_export_row(
        $output,
        ['Data inicial', report_export_date($period['display_start'] ?? '')]
    );

    report_export_row(
        $output,
        ['Data final', report_export_date($period['display_end'] ?? '')]
    );

    report_export_row(
        $output,
        ['Gerado em', date('d/m/Y H:i:s')]
    );

    report_export_row($output, []);
}

/**
 * @param resource $output
 * @param array<string,mixed> $report
 */
function report_export_company(
    $output,
    array $report,
    bool $canViewFinancial
): void {
    $summary = is_array($report['summary'] ?? null)
        ? $report['summary']
        : [];

    report_export_row($output, ['Indicador', 'Valor']);

    report_export_row(
        $output,
        ['OS finalizadas', (string) ($summary['orders'] ?? 0)]
    );

    report_export_row(
        $output,
        ['Clientes atendidos', (string) ($summary['unique_clients'] ?? 0)]
    );

    if ($canViewFinancial) {
        report_export_row(
            $output,
            [
                'Faturamento consolidado',
                report_export_money($summary['company_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Ticket médio',
                report_export_money($summary['average_ticket'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Total recebido',
                report_export_money($summary['received_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Saldo a receber',
                report_export_money($summary['receivable_balance'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Contas pendentes',
                (string) ($summary['pending_accounts'] ?? 0),
            ]
        );

        report_export_row(
            $output,
            [
                'Contas vencidas',
                (string) ($summary['overdue_accounts'] ?? 0),
            ]
        );

        report_export_row(
            $output,
            [
                'Serviços',
                report_export_money($summary['service_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Produtos',
                report_export_money($summary['product_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Outros',
                report_export_money($summary['other_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Descontos',
                report_export_money($summary['discount_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Acréscimos',
                report_export_money($summary['addition_total'] ?? '0'),
            ]
        );
    }

    report_export_row($output, []);
    report_export_row($output, ['Evolução diária']);

    $dailyHeader = [
        'Data',
        'OS finalizadas',
    ];

    if ($canViewFinancial) {
        $dailyHeader[] = 'Faturamento';
    }

    report_export_row($output, $dailyHeader);

    $dailyEvolution = is_array($report['daily_evolution'] ?? null)
        ? $report['daily_evolution']
        : [];

    foreach ($dailyEvolution as $day) {
        if (!is_array($day)) {
            continue;
        }

        $row = [
            report_export_date($day['date'] ?? ''),
            (string) ($day['orders'] ?? 0),
        ];

        if ($canViewFinancial) {
            $row[] = report_export_money($day['company_total'] ?? '0');
        }

        report_export_row($output, $row);
    }
}

/**
 * @param resource $output
 * @param array<string,mixed> $input
 * @param array<string,mixed> $firstReport
 */
function report_export_clients(
    $output,
    object $service,
    array $input,
    array $firstReport,
    bool $canViewFinancial
): void {
    $header = [
        'Código',
        'Cliente',
        'Documento',
        'Telefone',
        'Quantidade de OS',
        'Primeira OS',
        'Última OS',
    ];

    if ($canViewFinancial) {
        array_push(
            $header,
            'Faturamento',
            'Serviços',
            'Produtos',
            'Outros',
            'Descontos',
            'Acréscimos',
            'Ticket médio',
            'Recebido',
            'Saldo'
        );
    }

    report_export_row($output, $header);

    $report = $firstReport;
    $page = 1;

    while (true) {
        $rows = is_array($report['rows'] ?? null)
            ? $report['rows']
            : [];

        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = [
                (string) ($item['code'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ($item['document'] ?? ''),
                (string) ($item['phone'] ?? ''),
                (string) ($item['order_count'] ?? 0),
                report_export_datetime($item['first_finalized_at'] ?? ''),
                report_export_datetime($item['last_finalized_at'] ?? ''),
            ];

            if ($canViewFinancial) {
                $financial = is_array($item['financial'] ?? null)
                    ? $item['financial']
                    : [];

                array_push(
                    $row,
                    report_export_money($financial['executed_total'] ?? '0'),
                    report_export_money($financial['service_total'] ?? '0'),
                    report_export_money($financial['product_total'] ?? '0'),
                    report_export_money($financial['other_total'] ?? '0'),
                    report_export_money($financial['discount_total'] ?? '0'),
                    report_export_money($financial['addition_total'] ?? '0'),
                    report_export_money($financial['average_ticket'] ?? '0'),
                    report_export_money($financial['received_total'] ?? '0'),
                    report_export_money($financial['pending_balance'] ?? '0')
                );
            }

            report_export_row($output, $row);
        }

        $pagination = is_array($report['pagination'] ?? null)
            ? $report['pagination']
            : [];

        $lastPage = max(
            1,
            (int) ($pagination['last_page'] ?? 1)
        );

        if ($page >= $lastPage) {
            break;
        }

        ++$page;

        $input['page'] = $page;

        $report = $service->clientReport(
            $input,
            $canViewFinancial
        );
    }
}

/**
 * @param resource $output
 * @param array<string,mixed> $input
 * @param array<string,mixed> $firstReport
 */
function report_export_services(
    $output,
    object $service,
    array $input,
    array $firstReport,
    bool $canViewFinancial
): void {
    $header = [
        'Código',
        'Descrição histórica',
        'Nome atual',
        'Origem',
        'Quantidade executada',
        'Quantidade de OS',
        'Clientes atendidos',
        'Primeira execução',
        'Última execução',
    ];

    if ($canViewFinancial) {
        array_push(
            $header,
            'Faturamento',
            'Descontos',
            'Valor unitário médio',
            'Ticket médio por OS'
        );
    }

    report_export_row($output, $header);

    $report = $firstReport;
    $page = 1;

    while (true) {
        $rows = is_array($report['rows'] ?? null)
            ? $report['rows']
            : [];

        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = [
                (string) ($item['code'] ?? ''),
                (string) ($item['historical_description'] ?? ''),
                (string) ($item['current_name'] ?? ''),
                ((string) ($item['origin'] ?? 'manual')) === 'registered'
                    ? 'Cadastrado'
                    : 'Manual',
                report_export_decimal(
                    $item['quantity_total'] ?? '0',
                    3
                ),
                (string) ($item['order_count'] ?? 0),
                (string) ($item['client_count'] ?? 0),
                report_export_datetime($item['first_executed_at'] ?? ''),
                report_export_datetime($item['last_executed_at'] ?? ''),
            ];

            if ($canViewFinancial) {
                $financial = is_array($item['financial'] ?? null)
                    ? $item['financial']
                    : [];

                array_push(
                    $row,
                    report_export_money($financial['revenue_total'] ?? '0'),
                    report_export_money($financial['discount_total'] ?? '0'),
                    report_export_money($financial['average_unit_value'] ?? '0'),
                    report_export_money($financial['average_order_ticket'] ?? '0')
                );
            }

            report_export_row($output, $row);
        }

        $pagination = is_array($report['pagination'] ?? null)
            ? $report['pagination']
            : [];

        $lastPage = max(
            1,
            (int) ($pagination['last_page'] ?? 1)
        );

        if ($page >= $lastPage) {
            break;
        }

        ++$page;

        $input['page'] = $page;

        $report = $service->serviceReport(
            $input,
            $canViewFinancial
        );
    }
}

/**
 * @param resource $output
 * @param array<string,mixed> $report
 */
function report_export_team(
    $output,
    array $report,
    bool $canViewCommission
): void {
    $goal = is_array($report['goal'] ?? null)
        ? $report['goal']
        : [];

    $summary = is_array($report['summary'] ?? null)
        ? $report['summary']
        : [];

    report_export_row($output, ['Resumo da equipe']);

    report_export_row(
        $output,
        ['OS finalizadas', (string) ($summary['orders'] ?? 0)]
    );

    report_export_row(
        $output,
        [
            'Funcionários avaliados',
            (string) ($summary['employee_count'] ?? 0),
        ]
    );

    if ($canViewCommission) {
        report_export_row(
            $output,
            [
                'Metas atingidas',
                (string) ($summary['qualified_count'] ?? 0),
            ]
        );

        report_export_row(
            $output,
            [
                'Faturamento creditado',
                report_export_money($summary['company_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Serviços executados',
                report_export_money($summary['service_total'] ?? '0'),
            ]
        );

        report_export_row(
            $output,
            [
                'Meta aplicável',
                !empty($goal['applicable']) ? 'Sim' : 'Não',
            ]
        );

        report_export_row(
            $output,
            [
                'Meta configurada',
                !empty($goal['configured']) ? 'Sim' : 'Não',
            ]
        );

        if (!empty($goal['configured'])) {
            report_export_row(
                $output,
                [
                    'Meta individual',
                    report_export_money($goal['amount'] ?? '0'),
                ]
            );

            report_export_row(
                $output,
                [
                    'Percentual do prêmio',
                    report_export_decimal(
                        $goal['percentage'] ?? '0',
                        2
                    ) . '%',
                ]
            );
        }
    }

    report_export_row($output, []);
    report_export_row($output, ['Desempenho por funcionário']);

    $employeeHeader = [
        'Código',
        'Funcionário',
        'Função',
        'Quantidade de OS',
    ];

    if ($canViewCommission) {
        array_push(
            $employeeHeader,
            'Valor creditado',
            'Serviços',
            'Progresso',
            'Valor restante',
            'Valor excedente',
            'Prêmio estimado',
            'Meta atingida'
        );
    }

    report_export_row($output, $employeeHeader);

    $employees = is_array($report['employees'] ?? null)
        ? $report['employees']
        : [];

    foreach ($employees as $employee) {
        if (!is_array($employee)) {
            continue;
        }

        $row = [
            (string) ($employee['code'] ?? ''),
            (string) ($employee['name'] ?? ''),
            (string) ($employee['function'] ?? ''),
            (string) ($employee['orders'] ?? 0),
        ];

        if ($canViewCommission) {
            array_push(
                $row,
                report_export_money($employee['realized'] ?? '0'),
                report_export_money($employee['service_total'] ?? '0'),
                report_export_decimal(
                    $employee['progress_percent'] ?? '0',
                    2
                ) . '%',
                report_export_money($employee['remaining'] ?? '0'),
                report_export_money($employee['exceeded'] ?? '0'),
                report_export_money($employee['prize'] ?? '0'),
                !empty($employee['qualified']) ? 'Sim' : 'Não'
            );
        }

        report_export_row($output, $row);
    }

    report_export_row($output, []);
    report_export_row($output, ['OS que compõem a apuração']);

    $detailHeader = [
        'Funcionário',
        'Função',
        'OS',
        'Cliente',
        'Finalização',
    ];

    if ($canViewCommission) {
        $detailHeader[] = 'Serviços';
        $detailHeader[] = 'Total executado';
    }

    report_export_row($output, $detailHeader);

    $details = is_array($report['details'] ?? null)
        ? $report['details']
        : [];

    foreach ($details as $detail) {
        if (!is_array($detail)) {
            continue;
        }

        $row = [
            (string) ($detail['employee_name'] ?? ''),
            (string) ($detail['employee_function'] ?? ''),
            (string) ($detail['order_number'] ?? ''),
            (string) ($detail['client_name'] ?? ''),
            report_export_datetime($detail['finalized_at'] ?? ''),
        ];

        if ($canViewCommission) {
            $row[] = report_export_money(
                $detail['service_total'] ?? '0'
            );

            $row[] = report_export_money(
                $detail['executed_total'] ?? '0'
            );
        }

        report_export_row($output, $row);
    }
}

/**
 * @param resource $output
 * @param array<int,mixed> $values
 */
function report_export_row(
    $output,
    array $values
): void {
    $safeValues = array_map(
        static fn(mixed $value): string =>
            report_export_safe_cell($value),
        $values
    );

    fputcsv(
        $output,
        $safeValues,
        ';',
        '"',
        '\\'
    );
}

function report_export_safe_cell(mixed $value): string
{
    $text = str_replace(
        ["\r\n", "\r"],
        "\n",
        trim((string) $value)
    );

    if (
        $text !== ''
        && preg_match('/^[=+\-@\t]/u', $text) === 1
    ) {
        return "'" . $text;
    }

    return $text;
}

function report_export_money(mixed $value): string
{
    return 'R$ ' . report_export_decimal(
        $value,
        2
    );
}

function report_export_decimal(
    mixed $value,
    int $scale = 2
): string {
    $normalized = trim((string) $value);

    if (
        preg_match(
            '/^-?\d+(?:\.\d+)?$/',
            $normalized
        ) !== 1
    ) {
        $normalized = '0';
    }

    $negative = str_starts_with(
        $normalized,
        '-'
    );

    $unsigned = ltrim(
        $normalized,
        '-'
    );

    [$integer, $fraction] = array_pad(
        explode('.', $unsigned, 2),
        2,
        ''
    );

    $integer = ltrim(
        $integer,
        '0'
    );

    $integer = $integer === ''
        ? '0'
        : $integer;

    $fraction = substr(
        str_pad(
            $fraction,
            $scale,
            '0'
        ),
        0,
        $scale
    );

    return ($negative ? '-' : '')
        . number_format(
            (int) $integer,
            0,
            ',',
            '.'
        )
        . (
            $scale > 0
                ? ',' . $fraction
                : ''
        );
}

function report_export_date(mixed $value): string
{
    $text = trim((string) $value);

    $timestamp = $text === ''
        ? false
        : strtotime($text);

    return $timestamp === false
        ? ''
        : date('d/m/Y', $timestamp);
}

function report_export_datetime(mixed $value): string
{
    $text = trim((string) $value);

    $timestamp = $text === ''
        ? false
        : strtotime($text);

    return $timestamp === false
        ? ''
        : date('d/m/Y H:i', $timestamp);
}

/** @param array<string,mixed> $period */
function report_export_log_failure(
    Throwable $exception,
    string $section,
    ?int $companyId,
    array $period
): void {
    $entry = [
        'timestamp' => date('c'),
        'event' => 'report_export_failed',
        'section' => $section !== ''
            ? $section
            : 'unknown',
        'company_id' => $companyId,
        'period_start' => $period['start'] ?? null,
        'period_end_exclusive' => $period['end_exclusive'] ?? null,
        'exception_class' => get_class($exception),
        'exception_code' => (string) $exception->getCode(),
    ];

    error_log(
        json_encode(
            $entry,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: 'report_export_failed'
    );
}