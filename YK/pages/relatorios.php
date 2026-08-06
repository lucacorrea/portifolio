<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

function report_decimal(mixed $value): string
{
    $normalized = trim((string) $value);

    if ($normalized === '' || preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1) {
        return '0,00';
    }

    $negative = str_starts_with($normalized, '-');
    $unsigned = ltrim($normalized, '-');
    [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

    $integer = ltrim($integer, '0');
    $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

    return ($negative ? '-' : '')
        . number_format((int) ($integer === '' ? '0' : $integer), 0, ',', '.')
        . ','
        . $fraction;
}

function report_money(mixed $value): string
{
    return 'R$ ' . report_decimal($value);
}

function report_percent(mixed $value): string
{
    return report_decimal($value) . '%';
}

function report_quantity(mixed $value): string
{
    $formatted = number_format((float) $value, 3, ',', '.');

    return rtrim(rtrim($formatted, '0'), ',');
}

function report_date_time(mixed $value): string
{
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function report_payment_status(mixed $value): string
{
    return [
        'pendente' => 'Pendente',
        'parcial' => 'Parcial',
        'vencida' => 'Vencida',
        'paga' => 'Paga',
        'cancelada' => 'Cancelada',
    ][(string) $value] ?? 'Pendente';
}

/**
 * @param array<int,array{
 *     label:string,
 *     value:string,
 *     icon:string,
 *     note:string,
 *     tone?:string
 * }> $cards
 */
function report_metric_cards(array $cards): void
{
    if ($cards === []) {
        return;
    }
    ?>
    <div class="yk-report-metric-grid">
        <?php foreach ($cards as $card): ?>
            <?php $tone = trim((string) ($card['tone'] ?? 'neutral')); ?>
            <article class="yk-report-metric-card">
                <div class="yk-report-metric-head">
                    <span class="yk-report-metric-label"><?= h($card['label']) ?></span>
                    <span class="yk-report-metric-icon is-<?= h($tone) ?>" aria-hidden="true">
                        <i class="bi <?= h($card['icon']) ?>"></i>
                    </span>
                </div>

                <strong class="yk-report-metric-value"><?= h($card['value']) ?></strong>

                <?php if ($card['note'] !== ''): ?>
                    <span class="yk-report-metric-note"><?= h($card['note']) ?></span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

$requestedCompetence = trim((string) ($_GET['competencia'] ?? date('Y-m')));
$competence = preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $requestedCompetence) === 1
    ? $requestedCompetence
    : date('Y-m');

$canViewCommission = $authorization->can('relatorio.comissao.visualizar');

$canViewEmployees = $authorization->can('relatorio.funcionarios')
    || $authorization->can('relatorio.produtividade')
    || $canViewCommission;

$canViewOperations = $authorization->can('relatorio.operacional');
$canViewFinancial = $authorization->can('relatorio.financeiro');
$canViewStock = $authorization->can('relatorio.estoque');
$canViewStockCost = $canViewStock
    && $authorization->can('produto.visualizar_preco_custo');

$canViewCompany = $canViewOperations
    || $canViewFinancial
    || $canViewStock;

$canConfigureGoal = $authorization->can(
    'relatorio.meta_comissao.configurar'
);

$canLoadReport = $canViewCompany
    || $canViewEmployees
    || $canConfigureGoal;

$report = null;
$loadError = null;

if ($canLoadReport) {
    try {
        $report = $application->reports()->monthlyReport(
            $competence,
            [
                'operational' => $canViewOperations,
                'financial' => $canViewFinancial,
                'stock' => $canViewStock,
                'stock_cost' => $canViewStockCost,
                'employees' => $canViewEmployees,
                'commission' => $canViewCommission,
                'goal' => $canConfigureGoal,
            ]
        );
    } catch (Throwable $exception) {
        error_log(
            'Monthly report load failed: '
            . $exception->getMessage()
        );

        $loadError = 'Não foi possível carregar o relatório deste mês. Tente novamente.';
    }
}

$goal = is_array($report['goal'] ?? null)
    ? $report['goal']
    : [];

$summary = is_array($report['summary'] ?? null)
    ? $report['summary']
    : [];

$financial = is_array($report['financial'] ?? null)
    ? $report['financial']
    : [];

$inventory = is_array($report['inventory'] ?? null)
    ? $report['inventory']
    : [];

$companyDetails = is_array($report['company_details'] ?? null)
    ? $report['company_details']
    : [];

$serviceRanking = is_array($report['service_ranking'] ?? null)
    ? $report['service_ranking']
    : [];

$productRanking = is_array($report['product_ranking'] ?? null)
    ? $report['product_ranking']
    : [];

$employees = is_array($report['employees'] ?? null)
    ? $report['employees']
    : [];

$details = is_array($report['details'] ?? null)
    ? $report['details']
    : [];

$periodLabel = trim((string) ($report['period_label'] ?? ''))
    ?: $competence;

$goalConfigured = (bool) ($goal['configured'] ?? false);
$goalAmount = (string) ($goal['amount'] ?? '0.00');
$prizePercentage = (string) ($goal['percentage'] ?? '0.00');

$employeeOrderCount = count(
    array_unique(
        array_filter(
            array_column($details, 'order_number')
        )
    )
);

$allowedViews = [];

if ($canViewCompany) {
    $allowedViews[] = 'empresa';
}

if ($canViewEmployees) {
    $allowedViews[] = 'funcionarios';
}

$requestedView = is_array($_GET['visao'] ?? null)
    ? ''
    : trim((string) ($_GET['visao'] ?? ''));

$activeView = in_array(
    $requestedView,
    $allowedViews,
    true
)
    ? $requestedView
    : ($allowedViews[0] ?? '');

$viewDefinitions = [
    'empresa' => [
        'title' => 'Visão geral da empresa',
        'description' => 'Indicadores consolidados, composição financeira, estoque e rankings.',
        'icon' => 'bi-building',
    ],
    'funcionarios' => [
        'title' => 'Equipe, metas e comissão',
        'description' => 'Produção individual, metas mensais e prêmio estimado.',
        'icon' => 'bi-person-workspace',
    ],
];

$activeDefinition = $viewDefinitions[$activeView] ?? [
    'title' => 'Relatórios',
    'description' => 'Selecione uma visualização disponível.',
    'icon' => 'bi-bar-chart',
];

$companyCards = [];

if ($canViewOperations || $canViewFinancial) {
    $companyCards[] = [
        'label' => 'OS finalizadas',
        'value' => (string) ($summary['orders'] ?? 0),
        'icon' => 'bi-check2-circle',
        'note' => $periodLabel,
        'tone' => 'success',
    ];

    $companyCards[] = [
        'label' => 'Clientes atendidos',
        'value' => (string) ($summary['clients'] ?? 0),
        'icon' => 'bi-people',
        'note' => 'Clientes distintos no período',
        'tone' => 'primary',
    ];
}

if ($canViewFinancial) {
    $companyCards[] = [
        'label' => 'Faturamento consolidado',
        'value' => report_money(
            $summary['company_total'] ?? '0.00'
        ),
        'icon' => 'bi-cash-stack',
        'note' => 'Regime de competência',
        'tone' => 'primary',
    ];

    $companyCards[] = [
        'label' => 'Ticket médio',
        'value' => report_money(
            $summary['average_ticket'] ?? '0.00'
        ),
        'icon' => 'bi-receipt',
        'note' => 'Faturamento dividido pelas OS finalizadas',
        'tone' => 'neutral',
    ];

    $companyCards[] = [
        'label' => 'Já recebido',
        'value' => report_money(
            $summary['received_total'] ?? '0.00'
        ),
        'icon' => 'bi-wallet2',
        'note' => 'Posição atual da carteira',
        'tone' => 'success',
    ];

    $companyCards[] = [
        'label' => 'Saldo das OS',
        'value' => report_money(
            $summary['receivable_balance'] ?? '0.00'
        ),
        'icon' => 'bi-hourglass-split',
        'note' => 'Valor atualmente a receber',
        'tone' => 'warning',
    ];
}

if ($canViewStock) {
    $companyCards[] = [
        'label' => 'Produtos ativos',
        'value' => (string) ($inventory['active_products'] ?? 0),
        'icon' => 'bi-box-seam',
        'note' => 'Cadastro atual',
        'tone' => 'neutral',
    ];

    $companyCards[] = [
        'label' => 'Estoque crítico',
        'value' => (string) (
            ((int) ($inventory['low_stock'] ?? 0))
            + ((int) ($inventory['out_of_stock'] ?? 0))
        ),
        'icon' => 'bi-exclamation-triangle',
        'note' => 'Estoque baixo ou zerado',
        'tone' => 'danger',
    ];
}

$employeeCards = [
    [
        'label' => 'OS com equipe',
        'value' => (string) $employeeOrderCount,
        'icon' => 'bi-check2-circle',
        'note' => $periodLabel,
        'tone' => 'success',
    ],
    [
        'label' => 'Funcionários avaliados',
        'value' => (string) count($employees),
        'icon' => 'bi-people',
        'note' => 'Inclui cadastrados sem produção',
        'tone' => 'primary',
    ],
];

if ($canViewCommission) {
    $employeeCards[] = [
        'label' => 'Metas atingidas',
        'value' => (string) ($summary['qualified_count'] ?? 0),
        'icon' => 'bi-trophy',
        'note' => $goalConfigured
            ? 'Meta configurada para o mês'
            : 'Sem meta configurada',
        'tone' => 'warning',
    ];
}
?>

<style>
.reports-page {
    --yk-report-primary: #0f7f9a;
    --yk-report-primary-dark: #0b6178;
    --yk-report-primary-soft: #edf9fc;
    --yk-report-border: #dbe7ec;
    --yk-report-border-strong: #c9dce4;
    --yk-report-text: #10212b;
    --yk-report-muted: #687d89;
    --yk-report-soft: #f6fafb;
    --yk-report-success: #16834c;
    --yk-report-warning: #b56d0b;
    --yk-report-danger: #c53f4a;
    --yk-report-shadow: 0 10px 28px rgba(27, 61, 74, 0.07);
}

.reports-page *,
.reports-page *::before,
.reports-page *::after {
    box-sizing: border-box;
}

.yk-report-filter-panel,
.yk-report-period-summary,
.yk-report-selector-card,
.yk-report-workspace,
.yk-report-subpanel,
.yk-report-metric-card {
    border: 1px solid var(--yk-report-border);
    background: #ffffff;
}

.yk-report-filter-panel,
.yk-report-workspace {
    border-radius: 17px;
    box-shadow: var(--yk-report-shadow);
}

.yk-report-filter-panel {
    padding: 19px;
    margin-bottom: 18px;
}

.yk-report-filter-heading,
.yk-report-workspace-header,
.yk-report-period-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.yk-report-filter-heading {
    margin-bottom: 17px;
}

.yk-report-heading-copy,
.yk-report-workspace-copy {
    min-width: 0;
}

.yk-report-heading-title,
.yk-report-workspace-copy h2 {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: var(--yk-report-text);
    font-size: 1rem;
    font-weight: 750;
}

.yk-report-heading-title i,
.yk-report-workspace-copy h2 i {
    color: var(--yk-report-primary);
}

.yk-report-heading-copy p,
.yk-report-workspace-copy p,
.yk-report-period-summary p {
    margin: 4px 0 0;
    color: var(--yk-report-muted);
    font-size: 0.83rem;
    line-height: 1.45;
}

.yk-report-mode-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
    padding: 8px 13px;
    border: 1px solid var(--yk-report-border);
    border-radius: 11px;
    background: var(--yk-report-soft);
    color: var(--yk-report-primary-dark);
    font-size: 0.84rem;
    font-weight: 700;
}

.yk-report-filter-form {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
}

.yk-report-filter-field {
    flex: 1 1 300px;
    min-width: 220px;
}

.yk-report-filter-field label {
    display: block;
    margin-bottom: 7px;
    color: #516773;
    font-size: 0.79rem;
    font-weight: 700;
}

.yk-report-month-input {
    width: 100%;
    min-height: 43px;
    padding: 9px 13px;
    border: 1px solid var(--yk-report-border-strong);
    border-radius: 11px;
    background: #ffffff;
    color: var(--yk-report-text);
    font: inherit;
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.yk-report-month-input:focus {
    border-color: var(--yk-report-primary);
    box-shadow: 0 0 0 3px rgba(15, 127, 154, 0.13);
}

.yk-report-filter-actions,
.yk-report-workspace-actions {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
}

.yk-report-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    padding: 9px 15px;
    border: 1px solid var(--yk-report-border-strong);
    border-radius: 11px;
    background: #ffffff;
    color: var(--yk-report-text);
    font-size: 0.84rem;
    font-weight: 750;
    line-height: 1;
    text-decoration: none;
    cursor: pointer;
    transition:
        transform 0.18s ease,
        border-color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease;
}

.yk-report-button:hover,
.yk-report-button:focus-visible {
    border-color: #9fc7d4;
    color: var(--yk-report-primary-dark);
    transform: translateY(-1px);
    outline: none;
}

.yk-report-button.is-primary {
    border-color: var(--yk-report-primary);
    background: var(--yk-report-primary);
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 127, 154, 0.19);
}

.yk-report-button.is-primary:hover,
.yk-report-button.is-primary:focus-visible {
    border-color: var(--yk-report-primary-dark);
    background: var(--yk-report-primary-dark);
    color: #ffffff;
}

.yk-report-period-summary {
    padding: 13px 15px;
    margin-bottom: 18px;
    border-color: #cde7ef;
    border-radius: 13px;
    background: var(--yk-report-primary-soft);
}

.yk-report-period-summary strong {
    color: var(--yk-report-primary-dark);
}

.yk-report-period-summary p {
    margin: 0;
}

.yk-report-selector {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
    margin-bottom: 18px;
}

.yk-report-selector.is-single {
    grid-template-columns: minmax(0, 520px);
}

.yk-report-selector-card {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
    padding: 16px;
    border-radius: 14px;
    color: inherit;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 7px 20px rgba(31, 67, 81, 0.04);
    transition:
        transform 0.18s ease,
        border-color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease;
}

.yk-report-selector-card:hover,
.yk-report-selector-card:focus-visible {
    border-color: #9fcbd8;
    color: inherit;
    transform: translateY(-1px);
    outline: none;
}

.yk-report-selector-card.is-active {
    border-color: var(--yk-report-primary);
    background: var(--yk-report-primary-soft);
    box-shadow: 0 10px 24px rgba(15, 127, 154, 0.12);
}

.yk-report-selector-icon,
.yk-report-workspace-icon {
    display: inline-grid;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 12px;
    background: #eef4f6;
    color: #38515e;
}

.yk-report-selector-icon {
    width: 44px;
    height: 44px;
    font-size: 1.06rem;
}

.yk-report-selector-card.is-active .yk-report-selector-icon {
    background: #d7f0f6;
    color: var(--yk-report-primary-dark);
}

.yk-report-selector-copy {
    min-width: 0;
}

.yk-report-selector-copy strong,
.yk-report-selector-copy small {
    display: block;
}

.yk-report-selector-copy strong {
    color: var(--yk-report-text);
    font-size: 0.9rem;
}

.yk-report-selector-copy small {
    margin-top: 4px;
    color: var(--yk-report-muted);
    font-size: 0.75rem;
    line-height: 1.4;
}

.yk-report-workspace {
    overflow: hidden;
}

.yk-report-workspace-header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--yk-report-border);
}

.yk-report-workspace-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.yk-report-workspace-icon {
    width: 41px;
    height: 41px;
    font-size: 1rem;
}

.yk-report-workspace-content {
    min-height: 320px;
    padding: 18px;
}

.yk-report-information {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    margin-bottom: 16px;
    border: 1px solid #cfe6ee;
    border-radius: 12px;
    background: #f2fbfd;
    color: #43616e;
    font-size: 0.8rem;
    line-height: 1.5;
}

.yk-report-information i {
    flex: 0 0 auto;
    margin-top: 1px;
    color: var(--yk-report-primary);
}

.yk-report-metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}

.yk-report-metric-card {
    min-width: 0;
    padding: 17px;
    border-radius: 14px;
}

.yk-report-metric-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.yk-report-metric-label {
    color: var(--yk-report-muted);
    font-size: 0.79rem;
    font-weight: 700;
}

.yk-report-metric-icon {
    display: inline-grid;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    place-items: center;
    border-radius: 10px;
    background: #eef4f6;
    color: #48616d;
}

.yk-report-metric-icon.is-primary {
    background: #dff3f7;
    color: var(--yk-report-primary-dark);
}

.yk-report-metric-icon.is-success {
    background: #e8f7ef;
    color: var(--yk-report-success);
}

.yk-report-metric-icon.is-warning {
    background: #fff5df;
    color: var(--yk-report-warning);
}

.yk-report-metric-icon.is-danger {
    background: #fff0f1;
    color: var(--yk-report-danger);
}

.yk-report-metric-value {
    display: block;
    margin-top: 12px;
    color: var(--yk-report-text);
    font-size: clamp(1.24rem, 2vw, 1.72rem);
    line-height: 1.12;
    overflow-wrap: anywhere;
}

.yk-report-metric-note {
    display: block;
    margin-top: 7px;
    color: var(--yk-report-muted);
    font-size: 0.73rem;
    line-height: 1.4;
}

.yk-report-section-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.yk-report-section-grid.is-one-column {
    grid-template-columns: minmax(0, 1fr);
}

.yk-report-subpanel {
    min-width: 0;
    border-radius: 14px;
    overflow: hidden;
}

.yk-report-subpanel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 16px;
    border-bottom: 1px solid var(--yk-report-border);
}

.yk-report-subpanel-title {
    min-width: 0;
}

.yk-report-subpanel-title h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    color: var(--yk-report-text);
    font-size: 0.93rem;
    font-weight: 750;
}

.yk-report-subpanel-title h3 i {
    color: var(--yk-report-primary);
}

.yk-report-subpanel-title p {
    margin: 4px 0 0;
    color: var(--yk-report-muted);
    font-size: 0.75rem;
    line-height: 1.4;
}

.yk-report-subpanel-body {
    padding: 16px;
}

.yk-report-breakdown {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 11px;
}

.yk-report-breakdown.is-four {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.yk-report-breakdown-item {
    min-width: 0;
    padding: 13px;
    border-radius: 11px;
    background: var(--yk-report-soft);
}

.yk-report-breakdown-item.is-highlight {
    background: var(--yk-report-primary-soft);
}

.yk-report-breakdown-item span,
.yk-report-breakdown-item small {
    display: block;
    color: var(--yk-report-muted);
    font-size: 0.72rem;
    line-height: 1.4;
}

.yk-report-breakdown-item strong {
    display: block;
    margin-top: 5px;
    color: var(--yk-report-text);
    font-size: 0.9rem;
    overflow-wrap: anywhere;
}

.yk-report-ranking-list {
    display: grid;
    gap: 9px;
}

.yk-report-ranking-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 10px;
    background: var(--yk-report-soft);
}

.yk-report-ranking-copy {
    min-width: 0;
}

.yk-report-ranking-copy strong,
.yk-report-ranking-copy small {
    display: block;
}

.yk-report-ranking-copy strong {
    color: var(--yk-report-text);
    font-size: 0.8rem;
    overflow-wrap: anywhere;
}

.yk-report-ranking-copy small {
    margin-top: 3px;
    color: var(--yk-report-muted);
    font-size: 0.7rem;
}

.yk-report-ranking-value {
    flex: 0 0 auto;
    color: var(--yk-report-primary-dark);
    font-size: 0.79rem;
    font-weight: 750;
    text-align: right;
}

.yk-report-table-wrap {
    width: 100%;
    overflow: auto;
}

.yk-report-table {
    width: 100%;
    min-width: 760px;
    border-collapse: separate;
    border-spacing: 0;
}

.yk-report-table th,
.yk-report-table td {
    padding: 11px 12px;
    border-bottom: 1px solid #e7eef1;
    color: #344b57;
    font-size: 0.76rem;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
}

.yk-report-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7fafb;
    color: #536a75;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.yk-report-table tbody tr:hover td {
    background: #fbfdfe;
}

.yk-report-table td strong {
    color: var(--yk-report-text);
}

.yk-report-table td small {
    color: var(--yk-report-muted);
}

.yk-report-empty {
    display: grid;
    justify-items: center;
    gap: 8px;
    padding: 42px 20px;
    color: var(--yk-report-muted);
    text-align: center;
}

.yk-report-empty i {
    font-size: 1.5rem;
    color: #7d939d;
}

.yk-report-empty strong {
    color: #314a56;
}

.yk-report-empty p {
    max-width: 500px;
    margin: 0;
    font-size: 0.8rem;
}

.yk-report-alert {
    padding: 14px 15px;
    margin-bottom: 18px;
    border: 1px solid #f0b7bd;
    border-radius: 12px;
    background: #fff1f2;
    color: #9f2935;
    font-size: 0.84rem;
}

.yk-report-panel[hidden] {
    display: none !important;
}

@media (max-width: 1199.98px) {
    .yk-report-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .yk-report-breakdown.is-four {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .yk-report-filter-panel,
    .yk-report-workspace-content,
    .yk-report-workspace-header {
        padding: 14px;
    }

    .yk-report-selector,
    .yk-report-section-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .yk-report-filter-form,
    .yk-report-filter-actions,
    .yk-report-workspace-actions {
        width: 100%;
    }

    .yk-report-filter-field,
    .yk-report-button {
        width: 100%;
    }

    .yk-report-metric-grid,
    .yk-report-breakdown,
    .yk-report-breakdown.is-four {
        grid-template-columns: minmax(0, 1fr);
    }

    .yk-report-period-summary {
        align-items: flex-start;
    }

    .yk-report-period-summary p {
        width: 100%;
    }
}

@media print {
    .yk-report-filter-panel,
    .yk-report-period-summary,
    .yk-report-selector,
    .yk-report-workspace-actions,
    .yk-report-information,
    .modal,
    .sidebar,
    .app-sidebar,
    .topbar,
    .app-header {
        display: none !important;
    }

    .reports-page {
        padding: 0 !important;
    }

    .yk-report-workspace {
        border: 0;
        box-shadow: none;
    }

    .yk-report-workspace-header {
        padding: 0 0 14px;
    }

    .yk-report-workspace-content {
        padding: 0;
    }

    .yk-report-panel[hidden] {
        display: none !important;
    }

    .yk-report-table-wrap {
        overflow: visible;
    }

    .yk-report-table {
        min-width: 0;
    }

    .yk-report-table th,
    .yk-report-table td {
        padding: 6px;
        font-size: 9px;
        white-space: normal;
    }
}
</style>

<div class="page-body reports-page">
    <section class="yk-report-filter-panel">
        <div class="yk-report-filter-heading">
            <div class="yk-report-heading-copy">
                <h2 class="yk-report-heading-title">
                    <i class="bi bi-calendar3"></i>
                    Período do relatório
                </h2>
                <p>O mês selecionado será aplicado à visualização ativa.</p>
            </div>

            <span class="yk-report-mode-badge">
                <i class="bi bi-calendar-month"></i>
                Mensal
            </span>
        </div>

        <form
            class="yk-report-filter-form"
            method="get"
            action="relatorios.php"
        >
            <input
                type="hidden"
                id="report-active-view"
                name="visao"
                value="<?= h($activeView) ?>"
            >

            <div class="yk-report-filter-field">
                <label for="report-competence">
                    Mês de referência
                </label>

                <input
                    class="yk-report-month-input"
                    id="report-competence"
                    type="month"
                    name="competencia"
                    value="<?= h($competence) ?>"
                    required
                >
            </div>

            <div class="yk-report-filter-actions">
                <button
                    class="yk-report-button is-primary"
                    type="submit"
                >
                    <i class="bi bi-funnel"></i>
                    Aplicar período
                </button>

                <?php if ($canConfigureGoal): ?>
                    <button
                        class="yk-report-button"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-configurar-meta"
                    >
                        <i class="bi bi-bullseye"></i>
                        Configurar meta
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($loadError !== null): ?>
        <div class="yk-report-alert" role="alert">
            <?= h($loadError) ?>
        </div>
    <?php elseif (!$canViewCompany && !$canViewEmployees): ?>
        <?php
        empty_state(
            'Visualização de relatório indisponível',
            $canConfigureGoal
                ? 'Você pode configurar a meta, mas não possui permissão para visualizar os relatórios.'
                : 'Solicite uma permissão de relatório ao administrador.'
        );
        ?>
    <?php elseif ($report !== null): ?>
        <div class="yk-report-period-summary">
            <p>
                <strong>Período aplicado:</strong>
                <?= h($periodLabel) ?>
            </p>

            <p>Selecione um relatório para visualizar os dados.</p>
        </div>

        <nav
            class="yk-report-selector<?= count($allowedViews) === 1 ? ' is-single' : '' ?>"
            aria-label="Escolha do relatório"
        >
            <?php if ($canViewCompany): ?>
                <a
                    class="yk-report-selector-card<?= $activeView === 'empresa' ? ' is-active' : '' ?>"
                    id="report-selector-company"
                    href="relatorios.php?competencia=<?= h(rawurlencode($competence)) ?>&amp;visao=empresa"
                    role="tab"
                    aria-controls="report-panel-company"
                    aria-selected="<?= $activeView === 'empresa' ? 'true' : 'false' ?>"
                    data-report-tab="empresa"
                >
                    <span class="yk-report-selector-icon" aria-hidden="true">
                        <i class="bi bi-building"></i>
                    </span>

                    <span class="yk-report-selector-copy">
                        <strong>Visão geral da empresa</strong>
                        <small>
                            Indicadores consolidados, composição financeira,
                            estoque e rankings.
                        </small>
                    </span>
                </a>
            <?php endif; ?>

            <?php if ($canViewEmployees): ?>
                <a
                    class="yk-report-selector-card<?= $activeView === 'funcionarios' ? ' is-active' : '' ?>"
                    id="report-selector-employees"
                    href="relatorios.php?competencia=<?= h(rawurlencode($competence)) ?>&amp;visao=funcionarios"
                    role="tab"
                    aria-controls="report-panel-employees"
                    aria-selected="<?= $activeView === 'funcionarios' ? 'true' : 'false' ?>"
                    data-report-tab="funcionarios"
                >
                    <span class="yk-report-selector-icon" aria-hidden="true">
                        <i class="bi bi-person-workspace"></i>
                    </span>

                    <span class="yk-report-selector-copy">
                        <strong>Equipe, metas e comissão</strong>
                        <small>
                            Produção individual, metas mensais e prêmio estimado.
                        </small>
                    </span>
                </a>
            <?php endif; ?>
        </nav>

        <section class="yk-report-workspace">
            <header class="yk-report-workspace-header">
                <div class="yk-report-workspace-title">
                    <span class="yk-report-workspace-icon" aria-hidden="true">
                        <i
                            class="bi <?= h($activeDefinition['icon']) ?>"
                            data-report-workspace-icon
                        ></i>
                    </span>

                    <div class="yk-report-workspace-copy">
                        <h2 data-report-workspace-title>
                            <?= h($activeDefinition['title']) ?>
                        </h2>

                        <p data-report-workspace-description>
                            <?= h($activeDefinition['description']) ?>
                        </p>
                    </div>
                </div>

                <div class="yk-report-workspace-actions">
                    <button
                        class="yk-report-button"
                        type="button"
                        data-report-export
                    >
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        Exportar CSV
                    </button>

                    <button
                        class="yk-report-button"
                        type="button"
                        data-report-print
                    >
                        <i class="bi bi-printer"></i>
                        Imprimir
                    </button>
                </div>
            </header>

            <div class="yk-report-workspace-content">
                <?php if ($canViewCompany): ?>
                    <section
                        class="yk-report-panel"
                        id="report-panel-company"
                        role="<?= count($allowedViews) > 1 ? 'tabpanel' : 'region' ?>"
                        aria-labelledby="report-selector-company"
                        tabindex="0"
                        data-report-panel="empresa"
                        <?= $activeView === 'empresa' ? '' : 'hidden' ?>
                    >
                        <div class="yk-report-information" role="note">
                            <i class="bi bi-info-circle"></i>
                            <span>
                                A produção utiliza a data de finalização da OS.
                                O caixa utiliza a data real da movimentação.
                                Os saldos a receber mostram a posição atual das
                                OS finalizadas no período.
                            </span>
                        </div>

                        <?php report_metric_cards($companyCards); ?>

                        <?php if ($canViewFinancial): ?>
                            <div class="yk-report-section-grid">
                                <section class="yk-report-subpanel">
                                    <header class="yk-report-subpanel-header">
                                        <div class="yk-report-subpanel-title">
                                            <h3>
                                                <i class="bi bi-pie-chart"></i>
                                                Composição das OS
                                            </h3>
                                            <p>
                                                Distribuição dos valores das
                                                finalizações do período.
                                            </p>
                                        </div>
                                    </header>

                                    <div class="yk-report-subpanel-body">
                                        <div class="yk-report-breakdown">
                                            <div class="yk-report-breakdown-item">
                                                <span>Serviços</span>
                                                <strong>
                                                    <?= h(report_money($summary['service_total'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>Peças e produtos</span>
                                                <strong>
                                                    <?= h(report_money($summary['product_total'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>Outros</span>
                                                <strong>
                                                    <?= h(report_money($summary['other_total'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>Descontos</span>
                                                <strong>
                                                    <?= h(report_money($summary['discount_total'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>Acréscimos</span>
                                                <strong>
                                                    <?= h(report_money($summary['increase_total'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item is-highlight">
                                                <span>Total executado</span>
                                                <strong>
                                                    <?= h(report_money($summary['company_total'] ?? '0.00')) ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="yk-report-subpanel">
                                    <header class="yk-report-subpanel-header">
                                        <div class="yk-report-subpanel-title">
                                            <h3>
                                                <i class="bi bi-arrow-left-right"></i>
                                                Fluxo financeiro
                                            </h3>
                                            <p>
                                                Valores líquidos de estornos
                                                no regime de caixa.
                                            </p>
                                        </div>
                                    </header>

                                    <div class="yk-report-subpanel-body">
                                        <div class="yk-report-breakdown">
                                            <div class="yk-report-breakdown-item">
                                                <span>Entradas</span>
                                                <strong>
                                                    <?= h(report_money($financial['cash_in'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>Saídas</span>
                                                <strong>
                                                    <?= h(report_money($financial['cash_out'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item is-highlight">
                                                <span>Saldo líquido</span>
                                                <strong>
                                                    <?= h(report_money($financial['cash_net'] ?? '0.00')) ?>
                                                </strong>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>Despesas pagas</span>
                                                <strong>
                                                    <?= h(report_money($financial['paid_expenses'] ?? '0.00')) ?>
                                                </strong>
                                                <small>
                                                    <?= h((string) ($financial['paid_installments'] ?? 0)) ?>
                                                    parcela(s)
                                                </small>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>Vendas de peças</span>
                                                <strong>
                                                    <?= h(report_money($financial['sales_total'] ?? '0.00')) ?>
                                                </strong>
                                                <small>
                                                    <?= h((string) ($financial['sales'] ?? 0)) ?>
                                                    venda(s)
                                                </small>
                                            </div>

                                            <div class="yk-report-breakdown-item">
                                                <span>OS quitadas / saldo</span>
                                                <strong>
                                                    <?= h((string) ($summary['paid_orders'] ?? 0)) ?>
                                                    /
                                                    <?= h((string) ($summary['open_orders'] ?? 0)) ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        <?php endif; ?>

                        <?php if ($canViewStock): ?>
                            <section class="yk-report-subpanel" style="margin-bottom: 16px;">
                                <header class="yk-report-subpanel-header">
                                    <div class="yk-report-subpanel-title">
                                        <h3>
                                            <i class="bi bi-boxes"></i>
                                            Posição atual do estoque
                                        </h3>
                                        <p>
                                            Visão atual dos produtos cadastrados.
                                        </p>
                                    </div>
                                </header>

                                <div class="yk-report-subpanel-body">
                                    <div class="yk-report-breakdown is-four">
                                        <div class="yk-report-breakdown-item">
                                            <span>Produtos ativos</span>
                                            <strong>
                                                <?= h((string) ($inventory['active_products'] ?? 0)) ?>
                                            </strong>
                                        </div>

                                        <div class="yk-report-breakdown-item">
                                            <span>Estoque baixo</span>
                                            <strong>
                                                <?= h((string) ($inventory['low_stock'] ?? 0)) ?>
                                            </strong>
                                        </div>

                                        <div class="yk-report-breakdown-item">
                                            <span>Sem estoque</span>
                                            <strong>
                                                <?= h((string) ($inventory['out_of_stock'] ?? 0)) ?>
                                            </strong>
                                        </div>

                                        <?php if ($canViewStockCost): ?>
                                            <div class="yk-report-breakdown-item is-highlight">
                                                <span>Valor ao custo</span>
                                                <strong>
                                                    <?= h(report_money($inventory['stock_cost_value'] ?? '0.00')) ?>
                                                </strong>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if (
                            ($canViewOperations && $serviceRanking !== [])
                            || ($canViewStock && $productRanking !== [])
                        ): ?>
                            <div class="yk-report-section-grid">
                                <?php if ($canViewOperations && $serviceRanking !== []): ?>
                                    <section class="yk-report-subpanel">
                                        <header class="yk-report-subpanel-header">
                                            <div class="yk-report-subpanel-title">
                                                <h3>
                                                    <i class="bi bi-tools"></i>
                                                    Serviços mais executados
                                                </h3>
                                                <p>
                                                    Ranking por quantidade no período.
                                                </p>
                                            </div>
                                        </header>

                                        <div class="yk-report-subpanel-body">
                                            <div class="yk-report-ranking-list">
                                                <?php foreach ($serviceRanking as $item): ?>
                                                    <div class="yk-report-ranking-item">
                                                        <span class="yk-report-ranking-copy">
                                                            <strong>
                                                                <?= h((string) ($item['description'] ?? '—')) ?>
                                                            </strong>
                                                            <small>
                                                                <?= h((string) ($item['orders'] ?? 0)) ?>
                                                                OS ·
                                                                <?= h(report_quantity($item['quantity'] ?? 0)) ?>
                                                                <?= h((string) ($item['unit'] ?? '')) ?>
                                                            </small>
                                                        </span>

                                                        <?php if ($canViewFinancial): ?>
                                                            <strong class="yk-report-ranking-value">
                                                                <?= h(report_money($item['total'] ?? '0.00')) ?>
                                                            </strong>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                <?php endif; ?>

                                <?php if ($canViewStock && $productRanking !== []): ?>
                                    <section class="yk-report-subpanel">
                                        <header class="yk-report-subpanel-header">
                                            <div class="yk-report-subpanel-title">
                                                <h3>
                                                    <i class="bi bi-box-seam"></i>
                                                    Peças mais utilizadas
                                                </h3>
                                                <p>
                                                    Ranking por quantidade no período.
                                                </p>
                                            </div>
                                        </header>

                                        <div class="yk-report-subpanel-body">
                                            <div class="yk-report-ranking-list">
                                                <?php foreach ($productRanking as $item): ?>
                                                    <div class="yk-report-ranking-item">
                                                        <span class="yk-report-ranking-copy">
                                                            <strong>
                                                                <?= h((string) ($item['description'] ?? '—')) ?>
                                                            </strong>
                                                            <small>
                                                                <?= h((string) ($item['orders'] ?? 0)) ?>
                                                                OS ·
                                                                <?= h(report_quantity($item['quantity'] ?? 0)) ?>
                                                                <?= h((string) ($item['unit'] ?? '')) ?>
                                                            </small>
                                                        </span>

                                                        <?php if ($canViewFinancial): ?>
                                                            <strong class="yk-report-ranking-value">
                                                                <?= h(report_money($item['total'] ?? '0.00')) ?>
                                                            </strong>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($canViewOperations || $canViewFinancial): ?>
                            <section class="yk-report-subpanel">
                                <header class="yk-report-subpanel-header">
                                    <div class="yk-report-subpanel-title">
                                        <h3>
                                            <i class="bi bi-list-check"></i>
                                            OS que compõem o relatório
                                        </h3>
                                        <p>
                                            Até 200 finalizações mais recentes do mês.
                                        </p>
                                    </div>
                                </header>

                                <?php if ($companyDetails === []): ?>
                                    <div class="yk-report-empty">
                                        <i class="bi bi-inbox"></i>
                                        <strong>Sem detalhamento</strong>
                                        <p>
                                            Nenhuma OS finalizada válida foi
                                            encontrada para este mês.
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="yk-report-table-wrap">
                                        <table class="yk-report-table">
                                            <thead>
                                                <tr>
                                                    <th>OS</th>
                                                    <th>Cliente</th>
                                                    <th>Finalização</th>

                                                    <?php if ($canViewFinancial): ?>
                                                        <th>Serviços</th>
                                                        <th>Peças</th>
                                                        <th>Outros</th>
                                                        <th>Desconto</th>
                                                        <th>Acréscimo</th>
                                                        <th>Total</th>
                                                        <th>Recebido</th>
                                                        <th>Saldo</th>
                                                        <th>Situação</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php foreach ($companyDetails as $detail): ?>
                                                    <tr>
                                                        <td>
                                                            <strong>
                                                                <?= h((string) ($detail['order_number'] ?? '—')) ?>
                                                            </strong>
                                                        </td>

                                                        <td>
                                                            <?= h((string) ($detail['client_name'] ?? '—')) ?>
                                                        </td>

                                                        <td>
                                                            <?= h(report_date_time($detail['finalized_at'] ?? '')) ?>
                                                        </td>

                                                        <?php if ($canViewFinancial): ?>
                                                            <td>
                                                                <?= h(report_money($detail['service_total'] ?? '0.00')) ?>
                                                            </td>
                                                            <td>
                                                                <?= h(report_money($detail['product_total'] ?? '0.00')) ?>
                                                            </td>
                                                            <td>
                                                                <?= h(report_money($detail['other_total'] ?? '0.00')) ?>
                                                            </td>
                                                            <td>
                                                                <?= h(report_money($detail['discount_total'] ?? '0.00')) ?>
                                                            </td>
                                                            <td>
                                                                <?= h(report_money($detail['increase_total'] ?? '0.00')) ?>
                                                            </td>
                                                            <td>
                                                                <strong>
                                                                    <?= h(report_money($detail['executed_total'] ?? '0.00')) ?>
                                                                </strong>
                                                            </td>
                                                            <td>
                                                                <?= h(report_money($detail['received_total'] ?? '0.00')) ?>
                                                            </td>
                                                            <td>
                                                                <?= h(report_money($detail['balance'] ?? '0.00')) ?>
                                                            </td>
                                                            <td>
                                                                <?= ui_badge(
                                                                    report_payment_status(
                                                                        $detail['payment_status'] ?? 'pendente'
                                                                    )
                                                                ) ?>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($canViewEmployees): ?>
                    <section
                        class="yk-report-panel"
                        id="report-panel-employees"
                        role="<?= count($allowedViews) > 1 ? 'tabpanel' : 'region' ?>"
                        aria-labelledby="report-selector-employees"
                        tabindex="0"
                        data-report-panel="funcionarios"
                        <?= $activeView === 'funcionarios' ? '' : 'hidden' ?>
                    >
                        <div class="yk-report-information" role="note">
                            <i class="bi bi-info-circle"></i>
                            <span>
                                Cada integrante recebe o valor integral da OS
                                em que participou. Esses créditos são usados
                                para produtividade e não devem ser somados
                                como faturamento da empresa.
                            </span>
                        </div>

                        <?php report_metric_cards($employeeCards); ?>

                        <section class="yk-report-subpanel" style="margin-bottom: 16px;">
                            <header class="yk-report-subpanel-header">
                                <div class="yk-report-subpanel-title">
                                    <h3>
                                        <i class="bi bi-bullseye"></i>
                                        Meta de <?= h($periodLabel) ?>
                                    </h3>
                                    <p>
                                        Regra mensal aplicada à equipe.
                                    </p>
                                </div>

                                <?= $goalConfigured
                                    ? ui_badge('Ativo')
                                    : ui_badge('Pendente') ?>
                            </header>

                            <div class="yk-report-subpanel-body">
                                <?php if (!$goalConfigured): ?>
                                    <div class="yk-report-information" style="margin: 0;">
                                        <i class="bi bi-info-circle"></i>
                                        <span>
                                            Nenhuma meta foi configurada para
                                            este mês. A produção continua sendo
                                            apurada, mas não há prêmio estimado.
                                        </span>
                                    </div>
                                <?php elseif ($canViewCommission): ?>
                                    <div class="yk-report-breakdown">
                                        <div class="yk-report-breakdown-item is-highlight">
                                            <span>Meta individual</span>
                                            <strong>
                                                <?= h(report_money($goalAmount)) ?>
                                            </strong>
                                        </div>

                                        <div class="yk-report-breakdown-item">
                                            <span>Percentual do prêmio</span>
                                            <strong>
                                                <?= h(report_percent($prizePercentage)) ?>
                                            </strong>
                                        </div>

                                        <div class="yk-report-breakdown-item">
                                            <span>Regra</span>
                                            <strong>
                                                Percentual sobre todo o valor realizado
                                            </strong>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="yk-report-information" style="margin: 0;">
                                        <i class="bi bi-shield-lock"></i>
                                        <span>
                                            Há uma meta configurada para o
                                            período. Os valores e o prêmio
                                            estimado exigem permissão específica.
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="yk-report-subpanel" style="margin-bottom: 16px;">
                            <header class="yk-report-subpanel-header">
                                <div class="yk-report-subpanel-title">
                                    <h3>
                                        <i class="bi bi-person-lines-fill"></i>
                                        Desempenho por funcionário
                                    </h3>
                                    <p>
                                        Produção, progresso e premiação estimada.
                                    </p>
                                </div>
                            </header>

                            <?php if ($employees === []): ?>
                                <div class="yk-report-empty">
                                    <i class="bi bi-people"></i>
                                    <strong>Nenhum funcionário cadastrado</strong>
                                    <p>
                                        Cadastre a equipe para acompanhar a produtividade.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="yk-report-table-wrap">
                                    <table class="yk-report-table">
                                        <thead>
                                            <tr>
                                                <th>Funcionário</th>
                                                <th>Função</th>
                                                <th>OS</th>

                                                <?php if ($canViewCommission): ?>
                                                    <th>Valor creditado</th>
                                                    <th>Serviços</th>
                                                    <th>Progresso</th>
                                                    <th>Falta / excedente</th>
                                                    <th>Prêmio estimado</th>
                                                    <th>Situação</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($employees as $employee): ?>
                                                <?php
                                                $qualified = (bool) (
                                                    $employee['qualified'] ?? false
                                                );
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            <?= h((string) ($employee['name'] ?? '—')) ?>
                                                        </strong>
                                                        <br>
                                                        <small>
                                                            <?= h((string) ($employee['code'] ?? '')) ?>
                                                        </small>
                                                    </td>

                                                    <td>
                                                        <?= h((string) ($employee['function'] ?? '—')) ?>
                                                    </td>

                                                    <td>
                                                        <?= h((string) ($employee['orders'] ?? 0)) ?>
                                                    </td>

                                                    <?php if ($canViewCommission): ?>
                                                        <td>
                                                            <?= h(report_money($employee['realized'] ?? '0.00')) ?>
                                                        </td>
                                                        <td>
                                                            <?= h(report_money($employee['service_total'] ?? '0.00')) ?>
                                                        </td>
                                                        <td>
                                                            <?= h(report_percent($employee['progress_percent'] ?? '0.00')) ?>
                                                        </td>
                                                        <td>
                                                            <?= $qualified
                                                                ? h(report_money($employee['exceeded'] ?? '0.00')) . ' excedente'
                                                                : h(report_money($employee['remaining'] ?? '0.00')) . ' restante' ?>
                                                        </td>
                                                        <td>
                                                            <strong>
                                                                <?= h(report_money($employee['prize'] ?? '0.00')) ?>
                                                            </strong>
                                                        </td>
                                                        <td>
                                                            <?= ui_badge(
                                                                !$goalConfigured
                                                                    ? 'Sem meta'
                                                                    : (
                                                                        $qualified
                                                                            ? 'Meta atingida'
                                                                            : 'Em andamento'
                                                                    )
                                                            ) ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="yk-report-subpanel">
                            <header class="yk-report-subpanel-header">
                                <div class="yk-report-subpanel-title">
                                    <h3>
                                        <i class="bi bi-list-check"></i>
                                        OS por funcionário
                                    </h3>
                                    <p>
                                        Detalhamento das finalizações com equipe.
                                    </p>
                                </div>
                            </header>

                            <?php if ($details === []): ?>
                                <div class="yk-report-empty">
                                    <i class="bi bi-inbox"></i>
                                    <strong>Sem detalhamento</strong>
                                    <p>
                                        Nenhuma OS com equipe foi finalizada neste mês.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="yk-report-table-wrap">
                                    <table class="yk-report-table">
                                        <thead>
                                            <tr>
                                                <th>Funcionário</th>
                                                <th>Função</th>
                                                <th>OS</th>
                                                <th>Cliente</th>
                                                <th>Finalização</th>

                                                <?php if ($canViewCommission): ?>
                                                    <th>Serviços</th>
                                                    <th>Total executado</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($details as $detail): ?>
                                                <tr>
                                                    <td>
                                                        <?= h((string) ($detail['employee_name'] ?? '—')) ?>
                                                    </td>
                                                    <td>
                                                        <?= h((string) ($detail['employee_function'] ?? '—')) ?>
                                                    </td>
                                                    <td>
                                                        <strong>
                                                            <?= h((string) ($detail['order_number'] ?? '—')) ?>
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <?= h((string) ($detail['client_name'] ?? '—')) ?>
                                                    </td>
                                                    <td>
                                                        <?= h(report_date_time($detail['finalized_at'] ?? '')) ?>
                                                    </td>

                                                    <?php if ($canViewCommission): ?>
                                                        <td>
                                                            <?= h(report_money($detail['service_total'] ?? '0.00')) ?>
                                                        </td>
                                                        <td>
                                                            <?= h(report_money($detail['executed_total'] ?? '0.00')) ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>
                    </section>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php if ($canConfigureGoal): ?>
    <div
        class="modal fade"
        id="modal-configurar-meta"
        tabindex="-1"
        aria-hidden="true"
        aria-labelledby="report-goal-modal-title"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form
                class="modal-content visual-modal"
                method="post"
                action="actions/relatorio-meta-salvar.php"
                autocomplete="off"
            >
                <div class="modal-header">
                    <div>
                        <h2
                            class="modal-title fs-5"
                            id="report-goal-modal-title"
                        >
                            Configurar meta mensal
                        </h2>

                        <p class="text-muted small mb-0">
                            A regra valerá para todos os funcionários
                            no mês selecionado.
                        </p>
                    </div>

                    <button
                        class="btn-close"
                        type="button"
                        data-bs-dismiss="modal"
                        aria-label="Fechar"
                    ></button>
                </div>

                <div class="modal-body">
                    <?= $csrf->field() ?>
                    <?php return_to_field(); ?>

                    <div class="alert alert-info" role="note">
                        Ao atingir a meta, o percentual será aplicado
                        sobre todo o valor creditado ao funcionário,
                        e não apenas sobre o excedente.
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="goal-competence"
                            >
                                Mês de referência
                            </label>

                            <input
                                class="form-control-os"
                                id="goal-competence"
                                type="month"
                                name="competencia"
                                value="<?= h($competence) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="goal-amount"
                            >
                                Valor da meta
                            </label>

                            <input
                                class="form-control-os"
                                id="goal-amount"
                                name="valor_meta"
                                inputmode="decimal"
                                maxlength="20"
                                placeholder="11.000,00"
                                value="<?= $goalConfigured && $canViewCommission
                                    ? h(report_decimal($goalAmount))
                                    : '' ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="goal-percentage"
                            >
                                Percentual do prêmio
                            </label>

                            <div class="input-group">
                                <input
                                    class="form-control-os"
                                    id="goal-percentage"
                                    name="percentual_premio"
                                    inputmode="decimal"
                                    maxlength="8"
                                    placeholder="5,00"
                                    value="<?= $goalConfigured && $canViewCommission
                                        ? h(report_decimal($prizePercentage))
                                        : '' ?>"
                                    required
                                >

                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        class="btn-modal-cancel"
                        type="button"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        class="btn-modal-save"
                        type="submit"
                    >
                        <i class="bi bi-check-lg"></i>
                        Salvar meta
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
(() => {
    'use strict';

    const definitions = {
        empresa: {
            title: 'Visão geral da empresa',
            description: 'Indicadores consolidados, composição financeira, estoque e rankings.',
            icon: 'bi-building'
        },
        funcionarios: {
            title: 'Equipe, metas e comissão',
            description: 'Produção individual, metas mensais e prêmio estimado.',
            icon: 'bi-person-workspace'
        }
    };

    const tabLinks = Array.from(
        document.querySelectorAll('[data-report-tab]')
    );

    const panels = Array.from(
        document.querySelectorAll('[data-report-panel]')
    );

    const hiddenViewInput = document.getElementById(
        'report-active-view'
    );

    const workspaceTitle = document.querySelector(
        '[data-report-workspace-title]'
    );

    const workspaceDescription = document.querySelector(
        '[data-report-workspace-description]'
    );

    const workspaceIcon = document.querySelector(
        '[data-report-workspace-icon]'
    );

    const activateView = (view, updateUrl = true) => {
        if (!definitions[view]) {
            return;
        }

        let found = false;

        tabLinks.forEach((link) => {
            const active = link.dataset.reportTab === view;

            link.classList.toggle('is-active', active);
            link.setAttribute(
                'aria-selected',
                active ? 'true' : 'false'
            );

            if (active) {
                found = true;
            }
        });

        if (!found) {
            return;
        }

        panels.forEach((panel) => {
            panel.hidden = panel.dataset.reportPanel !== view;
        });

        if (hiddenViewInput) {
            hiddenViewInput.value = view;
        }

        const definition = definitions[view];

        if (workspaceTitle) {
            workspaceTitle.textContent = definition.title;
        }

        if (workspaceDescription) {
            workspaceDescription.textContent = definition.description;
        }

        if (workspaceIcon) {
            workspaceIcon.className = `bi ${definition.icon}`;
        }

        if (updateUrl && window.history?.replaceState) {
            const url = new URL(window.location.href);

            url.searchParams.set('visao', view);
            window.history.replaceState(
                {},
                '',
                url.toString()
            );
        }
    };

    tabLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const view = link.dataset.reportTab;

            if (!view) {
                return;
            }

            event.preventDefault();
            activateView(view);
        });
    });

    document
        .querySelector('[data-report-print]')
        ?.addEventListener('click', () => {
            window.print();
        });

    document
        .querySelector('[data-report-export]')
        ?.addEventListener('click', () => {
            const visiblePanel = panels.find(
                (panel) => !panel.hidden
            );

            if (!visiblePanel) {
                return;
            }

            const rows = [];
            const title = workspaceTitle?.textContent?.trim()
                || 'Relatório';

            rows.push([title]);
            rows.push(['Período', <?= json_encode(
                $periodLabel,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ) ?>]);
            rows.push([]);

            visiblePanel
                .querySelectorAll('table')
                .forEach((table, tableIndex) => {
                    if (tableIndex > 0) {
                        rows.push([]);
                    }

                    table
                        .querySelectorAll('tr')
                        .forEach((row) => {
                            rows.push(
                                Array.from(
                                    row.querySelectorAll('th, td')
                                ).map((cell) => (
                                    cell.textContent
                                        ?.replace(/\s+/g, ' ')
                                        .trim()
                                    || ''
                                ))
                            );
                        });
                });

            if (rows.length <= 3) {
                visiblePanel
                    .querySelectorAll(
                        '.yk-report-metric-card, .yk-report-breakdown-item'
                    )
                    .forEach((card) => {
                        const label = card
                            .querySelector(
                                '.yk-report-metric-label, span'
                            )
                            ?.textContent
                            ?.trim()
                            || '';

                        const value = card
                            .querySelector(
                                '.yk-report-metric-value, strong'
                            )
                            ?.textContent
                            ?.trim()
                            || '';

                        if (label || value) {
                            rows.push([label, value]);
                        }
                    });
            }

            const escapeCsv = (value) => {
                const normalized = String(value ?? '');

                return `"${normalized.replace(/"/g, '""')}"`;
            };

            const csv = rows
                .map((row) => row.map(escapeCsv).join(';'))
                .join('\r\n');

            const blob = new Blob(
                ['\uFEFF' + csv],
                {
                    type: 'text/csv;charset=utf-8'
                }
            );

            const link = document.createElement('a');
            const view = hiddenViewInput?.value || 'relatorio';

            link.href = URL.createObjectURL(blob);
            link.download = `relatorio-${view}-<?= h($competence) ?>.csv`;

            document.body.appendChild(link);
            link.click();
            link.remove();

            setTimeout(
                () => URL.revokeObjectURL(link.href),
                1000
            );
        });
})();
</script>