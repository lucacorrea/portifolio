<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

/*
 * =========================================================
 * PERMISSÕES DA EMPRESA ATIVA
 * =========================================================
 */
$canViewFinancial = $companyScope->allows('relatorio.financeiro');

$canViewCompanyReport = $companyScope->allows('relatorio.operacional')
    || $companyScope->allows('relatorio.produtividade')
    || $canViewFinancial;

$canViewClientReport = $companyScope->allows('relatorio.operacional')
    || $canViewFinancial;

$canViewServiceReport = $companyScope->allows('relatorio.operacional')
    || $companyScope->allows('relatorio.produtividade');

$canViewCommission = $companyScope->allows(
    'relatorio.comissao.visualizar'
);

$canViewTeamReport = $companyScope->allows('relatorio.funcionarios')
    || $companyScope->allows('relatorio.produtividade')
    || $canViewCommission;

$canConfigureGoal = $companyScope->allows(
    'relatorio.meta_comissao.configurar'
);

$hasAnyVisibleReport = $canViewCompanyReport
    || $canViewClientReport
    || $canViewServiceReport
    || $canViewTeamReport;

/*
 * =========================================================
 * FORMATADORES LOCAIS
 * =========================================================
 *
 * Não utilizam float para valores financeiros.
 */
$reportDecimal = static function (
    mixed $value,
    int $scale = 2
): string {
    $normalized = trim((string) $value);

    if (
        $normalized === ''
        || preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1
    ) {
        $normalized = '0';
    }

    $negative = str_starts_with($normalized, '-');
    $unsigned = ltrim($normalized, '-');

    [$integer, $fraction] = array_pad(
        explode('.', $unsigned, 2),
        2,
        ''
    );

    $integer = ltrim($integer, '0');
    $integer = $integer === '' ? '0' : $integer;

    $fraction = substr(
        str_pad($fraction, $scale, '0'),
        0,
        $scale
    );

    return ($negative ? '-' : '')
        . number_format((int) $integer, 0, ',', '.')
        . ($scale > 0 ? ',' . $fraction : '');
};

$reportMoney = static fn(mixed $value): string =>
    'R$ ' . $reportDecimal($value, 2);

$reportPercent = static fn(mixed $value): string =>
    $reportDecimal($value, 2) . '%';

$reportDate = static function (mixed $value): string {
    $text = trim((string) $value);
    $timestamp = $text === '' ? false : strtotime($text);

    return $timestamp === false
        ? '—'
        : date('d/m/Y', $timestamp);
};

$decimalToCents = static function (mixed $value): int {
    $normalized = trim((string) $value);

    if (preg_match('/^(\d+)(?:\.(\d+))?$/', $normalized, $matches) !== 1) {
        return 0;
    }

    $integer = ltrim($matches[1], '0');
    $integer = $integer === '' ? '0' : $integer;

    $fraction = substr(
        str_pad((string) ($matches[2] ?? ''), 2, '0'),
        0,
        2
    );

    if (strlen($integer) > 15) {
        return PHP_INT_MAX;
    }

    $whole = (int) $integer;

    if ($whole > intdiv(PHP_INT_MAX - 99, 100)) {
        return PHP_INT_MAX;
    }

    return ($whole * 100) + (int) $fraction;
};

$comparisonHtml = static function (
    mixed $comparison
) use (
    $reportPercent
): string {
    if (!is_array($comparison)) {
        return '<span class="report-comparison is-neutral">Sem comparação</span>';
    }

    $direction = (string) ($comparison['direction'] ?? 'stable');
    $comparable = (bool) ($comparison['comparable'] ?? false);
    $percentage = $comparison['percentage'] ?? null;

    if (!$comparable) {
        return '<span class="report-comparison is-neutral">'
            . '<i class="bi bi-dash-circle" aria-hidden="true"></i>'
            . ' Sem base anterior</span>';
    }

    if ($direction === 'up') {
        return '<span class="report-comparison is-positive">'
            . '<i class="bi bi-arrow-up-right" aria-hidden="true"></i> '
            . h($reportPercent($percentage))
            . ' vs. anterior</span>';
    }

    if ($direction === 'down') {
        return '<span class="report-comparison is-negative">'
            . '<i class="bi bi-arrow-down-right" aria-hidden="true"></i> '
            . h($reportPercent($percentage))
            . ' vs. anterior</span>';
    }

    return '<span class="report-comparison is-neutral">'
        . '<i class="bi bi-dash" aria-hidden="true"></i> '
        . h($reportPercent($percentage))
        . ' vs. anterior</span>';
};

$metricCard = static function (
    string $label,
    string $value,
    string $icon,
    string $note = '',
    string $comparison = ''
): string {
    $html = '<article class="report-metric-card">'
        . '<div class="report-metric-head">'
        . '<span class="report-metric-label">' . h($label) . '</span>'
        . '<span class="report-metric-icon"><i class="bi '
        . h($icon)
        . '" aria-hidden="true"></i></span>'
        . '</div>'
        . '<strong class="report-metric-value">' . h($value) . '</strong>';

    if ($comparison !== '') {
        $html .= $comparison;
    }

    if ($note !== '') {
        $html .= '<small class="report-metric-note">' . h($note) . '</small>';
    }

    return $html . '</article>';
};

/*
 * =========================================================
 * PERÍODO E VISÃO GERAL
 * =========================================================
 */
$periodError = null;
$loadError = null;
$period = [];
$companyReport = null;

try {
    $period = $application->reports()->resolvePeriod($_GET);
} catch (InvalidArgumentException $exception) {
    $periodError = $exception->getMessage();

    $period = $application->reports()->resolvePeriod([
        'modo' => 'mes',
        'competencia' => date('Y-m'),
    ]);
}

if ($canViewCompanyReport) {
    try {
        $companyReport = $application->reports()->companyReport(
            is_array($period['query'] ?? null)
                ? $period['query']
                : [],
            $canViewFinancial
        );
    } catch (Throwable $exception) {
        error_log(
            json_encode(
                [
                    'event' => 'report_company_load_failed',
                    'company_id' => $companyScope->id(),
                    'period_start' => $period['start'] ?? null,
                    'period_end_exclusive' => $period['end_exclusive'] ?? null,
                    'exception_class' => get_class($exception),
                    'exception_code' => (string) $exception->getCode(),
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            ) ?: 'report_company_load_failed'
        );

        $loadError = 'Não foi possível carregar a visão geral do relatório.';
    }
}

$summary = is_array($companyReport['summary'] ?? null)
    ? $companyReport['summary']
    : [];

$comparison = is_array($companyReport['comparison'] ?? null)
    ? $companyReport['comparison']
    : [];

$dailyEvolution = is_array($companyReport['daily_evolution'] ?? null)
    ? $companyReport['daily_evolution']
    : [];

$rankings = is_array($companyReport['rankings'] ?? null)
    ? $companyReport['rankings']
    : [];

$periodMode = (string) ($period['mode'] ?? 'month');
$periodCompetence = (string) (
    $period['competence']
    ?? date('Y-m')
);
$periodStart = (string) (
    $period['display_start']
    ?? date('Y-m-01')
);
$periodEnd = (string) (
    $period['display_end']
    ?? date('Y-m-t')
);
$periodLabel = (string) (
    $period['label']
    ?? 'Período selecionado'
);
$previousPeriodLabel = (string) (
    $period['previous_label']
    ?? 'período anterior'
);

/*
 * Largura máxima dos indicadores diários sem uso de float.
 */
$maxDailyValue = 0;

foreach ($dailyEvolution as $day) {
    if (!is_array($day)) {
        continue;
    }

    $value = $canViewFinancial
        ? $decimalToCents($day['company_total'] ?? '0')
        : (int) ($day['orders'] ?? 0);

    $maxDailyValue = max($maxDailyValue, $value);
}

$goalCompetence = $periodMode === 'month'
    ? $periodCompetence
    : date('Y-m');
?>

<style>
.reports-page {
    --report-border: #e5e7eb;
    --report-muted: #64748b;
    --report-surface: #ffffff;
    --report-soft: #f8fafc;
    --report-primary: #2563eb;
}

.report-filter-panel,
.report-overview-panel,
.report-accordion-item {
    border: 1px solid var(--report-border);
    border-radius: 16px;
    background: var(--report-surface);
    box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
}

.report-filter-panel {
    padding: 18px;
    margin-bottom: 20px;
}

.report-filter-heading,
.report-period-identification,
.report-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.report-filter-heading {
    margin-bottom: 16px;
}

.report-filter-heading h2,
.report-section-heading h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.report-filter-heading p,
.report-period-identification p {
    margin: 4px 0 0;
    color: var(--report-muted);
    font-size: .875rem;
}

.report-mode-selector {
    display: inline-flex;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--report-border);
    border-radius: 12px;
    background: var(--report-soft);
}

.report-mode-selector label {
    position: relative;
    margin: 0;
    cursor: pointer;
}

.report-mode-selector input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.report-mode-selector span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
    padding: 8px 13px;
    border-radius: 9px;
    color: #475569;
    font-size: .875rem;
    font-weight: 600;
}

.report-mode-selector input:checked + span {
    background: #fff;
    color: var(--report-primary);
    box-shadow: 0 2px 8px rgba(15, 23, 42, .08);
}

.report-filter-fields {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
}

.report-filter-fields .form-group {
    min-width: 190px;
    flex: 1 1 190px;
    margin: 0;
}

.report-filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.report-period-identification {
    padding: 14px 16px;
    margin-bottom: 20px;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    background: #eff6ff;
}

.report-period-identification strong {
    color: #1d4ed8;
}

.report-metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.report-metric-card {
    min-width: 0;
    padding: 17px;
    border: 1px solid var(--report-border);
    border-radius: 14px;
    background: #fff;
}

.report-metric-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.report-metric-label {
    color: var(--report-muted);
    font-size: .82rem;
    font-weight: 600;
}

.report-metric-icon {
    display: inline-grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 10px;
    background: #f1f5f9;
    color: #334155;
}

.report-metric-value {
    display: block;
    margin-top: 12px;
    color: #0f172a;
    font-size: clamp(1.25rem, 2vw, 1.75rem);
    line-height: 1.1;
    overflow-wrap: anywhere;
}

.report-metric-note,
.report-comparison {
    display: block;
    margin-top: 7px;
    font-size: .75rem;
}

.report-metric-note,
.report-comparison.is-neutral {
    color: var(--report-muted);
}

.report-comparison.is-positive {
    color: #15803d;
}

.report-comparison.is-negative {
    color: #b91c1c;
}

.report-overview-panel {
    overflow: hidden;
}

.report-section-heading {
    padding: 16px 18px;
    border-bottom: 1px solid var(--report-border);
}

.report-section-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.report-section-body {
    padding: 18px;
}

.report-breakdown-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin-top: 16px;
}

.report-breakdown-item {
    padding: 14px;
    border-radius: 12px;
    background: var(--report-soft);
}

.report-breakdown-item span,
.report-ranking-item span {
    display: block;
    color: var(--report-muted);
    font-size: .78rem;
}

.report-breakdown-item strong {
    display: block;
    margin-top: 5px;
    color: #0f172a;
}

.report-dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(300px, .8fr);
    gap: 16px;
    margin-top: 16px;
}

.report-subpanel {
    min-width: 0;
    padding: 16px;
    border: 1px solid var(--report-border);
    border-radius: 14px;
    background: #fff;
}

.report-subpanel h4 {
    margin: 0 0 4px;
    color: #0f172a;
    font-size: .95rem;
    font-weight: 700;
}

.report-subpanel > p {
    margin: 0 0 14px;
    color: var(--report-muted);
    font-size: .8rem;
}

.report-evolution-scroll {
    overflow-x: auto;
    padding-bottom: 4px;
}

.report-evolution-chart {
    display: flex;
    align-items: end;
    gap: 7px;
    min-width: max-content;
    height: 210px;
    padding: 12px 4px 0;
}

.report-evolution-day {
    display: grid;
    grid-template-rows: 1fr auto auto;
    align-items: end;
    width: 42px;
    height: 100%;
    text-align: center;
}

.report-evolution-bar-wrap {
    display: flex;
    align-items: end;
    justify-content: center;
    height: 150px;
}

.report-evolution-bar {
    width: 22px;
    min-height: 3px;
    border-radius: 7px 7px 3px 3px;
    background: #2563eb;
}

.report-evolution-day strong {
    margin-top: 6px;
    color: #334155;
    font-size: .68rem;
    white-space: nowrap;
}

.report-evolution-day small {
    color: var(--report-muted);
    font-size: .65rem;
}

.report-ranking-list {
    display: grid;
    gap: 9px;
}

.report-ranking-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eef2f7;
}

.report-ranking-item:last-child {
    border-bottom: 0;
}

.report-ranking-item strong {
    display: block;
    overflow: hidden;
    color: #1e293b;
    font-size: .85rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.report-ranking-value {
    color: #0f172a;
    font-size: .82rem;
    font-weight: 700;
    text-align: right;
    white-space: nowrap;
}

.report-accordion {
    display: grid;
    gap: 12px;
    margin-top: 20px;
}

.report-accordion-item {
    overflow: hidden;
}

.report-accordion-button {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border: 0;
    background: #fff;
    color: #0f172a;
    text-align: left;
}

.report-accordion-button:hover,
.report-accordion-button:focus-visible {
    background: #f8fafc;
}

.report-accordion-button > span:first-child {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
    font-weight: 700;
}

.report-accordion-button small {
    display: block;
    margin-top: 3px;
    color: var(--report-muted);
    font-weight: 400;
}

.report-accordion-chevron {
    transition: transform .2s ease;
}

.report-accordion-button[aria-expanded="true"] .report-accordion-chevron {
    transform: rotate(180deg);
}

.report-accordion-content {
    padding: 0 18px 18px;
    border-top: 1px solid var(--report-border);
}

.report-section-state {
    display: grid;
    place-items: center;
    min-height: 180px;
    padding: 28px;
    text-align: center;
}

.report-section-state > i {
    margin-bottom: 10px;
    color: #64748b;
    font-size: 1.6rem;
}

.report-section-state strong {
    color: #0f172a;
}

.report-section-state p {
    max-width: 520px;
    margin: 6px 0 14px;
    color: var(--report-muted);
}

.report-loading-icon {
    animation: report-spin .8s linear infinite;
}

@keyframes report-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 1200px) {
    .report-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-breakdown-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .report-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .report-filter-panel,
    .report-section-body,
    .report-accordion-content {
        padding: 14px;
    }

    .report-metric-grid,
    .report-breakdown-grid {
        grid-template-columns: 1fr;
    }

    .report-filter-fields,
    .report-filter-actions {
        align-items: stretch;
    }

    .report-filter-actions > * {
        width: 100%;
        justify-content: center;
    }

    .report-mode-selector {
        width: 100%;
    }

    .report-mode-selector label {
        flex: 1;
    }

    .report-mode-selector span {
        justify-content: center;
    }
}

@media print {
    body * {
        visibility: hidden !important;
    }

    [data-report-page],
    [data-report-page] * {
        visibility: visible !important;
    }

    [data-report-page] {
        position: absolute;
        inset: 0;
        width: 100%;
    }

    .report-filter-panel,
    .report-section-actions,
    .report-accordion-button,
    .modal,
    button,
    .btn-filter {
        display: none !important;
    }

    .collapse {
        display: block !important;
    }

    [data-report-page][data-printing-section] [data-report-print-target] {
        display: none !important;
    }

    [data-report-page][data-printing-section="empresa"] [data-report-print-target="empresa"],
    [data-report-page][data-printing-section="clientes"] [data-report-print-target="clientes"],
    [data-report-page][data-printing-section="servicos"] [data-report-print-target="servicos"],
    [data-report-page][data-printing-section="equipe"] [data-report-print-target="equipe"] {
        display: block !important;
    }
}
</style>

<div
    class="page-body reports-page"
    data-report-page
    data-section-endpoint="actions/relatorio-secao-carregar.php"
>
    <section class="report-filter-panel" aria-labelledby="report-filter-title">
        <form
            id="report-filter-form"
            method="get"
            action="relatorios.php"
            data-report-filter-form
            autocomplete="off"
        >
        <div class="report-filter-heading">
            <div>
                <h2 id="report-filter-title">
                    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
                    Período do relatório
                </h2>

                <p>
                    Use um mês completo ou selecione um intervalo personalizado de até 366 dias.
                </p>
            </div>

            <div class="report-mode-selector" role="radiogroup" aria-label="Modo do período">
                <label>
                    <input
                        type="radio"
                        name="modo"
                        value="mes"
                        <?= $periodMode === 'month' ? 'checked' : '' ?>
                    >
                    <span>
                        <i class="bi bi-calendar-month"></i>
                        Mensal
                    </span>
                </label>

                <label>
                    <input
                        type="radio"
                        name="modo"
                        value="periodo"
                        <?= $periodMode === 'custom' ? 'checked' : '' ?>
                    >
                    <span>
                        <i class="bi bi-calendar-range"></i>
                        Personalizado
                    </span>
                </label>
            </div>
        </div>

        <div class="report-filter-fields">
            <div
                class="form-group"
                data-period-month-fields
                <?= $periodMode !== 'month' ? 'hidden' : '' ?>
            >
                <label class="form-label" for="report-competence">
                    Mês de referência
                </label>

                <input
                    class="form-control-os"
                    id="report-competence"
                    type="month"
                    name="competencia"
                    min="2000-01"
                    max="2100-12"
                    value="<?= h($periodCompetence) ?>"
                    <?= $periodMode !== 'month' ? 'disabled' : '' ?>
                    required
                >
            </div>

            <div
                class="form-group"
                data-period-custom-fields
                <?= $periodMode === 'month' ? 'hidden' : '' ?>
            >
                <label class="form-label" for="report-start-date">
                    Data inicial
                </label>

                <input
                    class="form-control-os"
                    id="report-start-date"
                    type="date"
                    name="data_inicial"
                    min="2000-01-01"
                    max="2100-12-31"
                    value="<?= h($periodStart) ?>"
                    <?= $periodMode === 'month' ? 'disabled' : '' ?>
                    required
                >
            </div>

            <div
                class="form-group"
                data-period-custom-fields
                <?= $periodMode === 'month' ? 'hidden' : '' ?>
            >
                <label class="form-label" for="report-end-date">
                    Data final
                </label>

                <input
                    class="form-control-os"
                    id="report-end-date"
                    type="date"
                    name="data_final"
                    min="2000-01-01"
                    max="2100-12-31"
                    value="<?= h($periodEnd) ?>"
                    <?= $periodMode === 'month' ? 'disabled' : '' ?>
                    required
                >
            </div>

            <div class="report-filter-actions">
                <button class="btn-filter btn-filter-primary" type="submit">
                    <i class="bi bi-funnel"></i>
                    Aplicar período
                </button>

                <a class="btn-filter btn-filter-ghost" href="relatorios.php">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Limpar
                </a>

                <?php if ($canConfigureGoal): ?>
                    <button
                        class="btn-filter btn-filter-ghost"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-configurar-meta"
                    >
                        <i class="bi bi-bullseye"></i>
                        Configurar meta
                    </button>
                <?php endif; ?>
            </div>
        </div>
        </form>
    </section>

    <?php if ($periodError !== null): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= h($periodError) ?>
            O mês atual foi carregado como alternativa segura.
        </div>
    <?php endif; ?>

    <div class="report-period-identification" role="status">
        <div>
            <strong><?= h($periodLabel) ?></strong>
            <p>
                De <?= h($reportDate($periodStart)) ?> até <?= h($reportDate($periodEnd)) ?>.
                Comparação com <?= h($previousPeriodLabel) ?>.
            </p>
        </div>

        <span class="badge-soft badge-blue">
            <?= h((string) ($period['days'] ?? 0)) ?> dia(s)
        </span>
    </div>

    <?php if (!$hasAnyVisibleReport): ?>
        <?php
        empty_state(
            'Nenhum relatório disponível para este perfil',
            'Solicite ao administrador uma permissão de relatório compatível.'
        );
        ?>
    <?php endif; ?>

    <?php if ($canViewCompanyReport): ?>
        <section class="report-overview-panel" aria-labelledby="company-report-title" data-report-print-target="empresa">
            <div class="report-section-heading">
                <div>
                    <h3 id="company-report-title">
                        <i class="bi bi-buildings me-1"></i>
                        Visão geral da empresa
                    </h3>
                    <p class="section-note mb-0">
                        Indicadores consolidados das OS finalizadas no período.
                    </p>
                </div>

                <div class="report-section-actions">
                    <button
                        class="btn-filter btn-filter-ghost"
                        type="button"
                        data-report-print-section="empresa"
                    >
                        <i class="bi bi-printer"></i>
                        Imprimir
                    </button>
                </div>
            </div>

            <div class="report-section-body">
                <?php if ($loadError !== null): ?>
                    <div class="alert alert-danger mb-0" role="alert">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= h($loadError) ?>
                    </div>
                <?php elseif ($companyReport !== null): ?>
                    <div class="report-metric-grid">
                        <?= $metricCard(
                            'OS finalizadas',
                            (string) ($summary['orders'] ?? 0),
                            'bi-check2-circle',
                            $periodLabel,
                            $comparisonHtml($comparison['orders'] ?? null)
                        ) ?>

                        <?= $metricCard(
                            'Clientes atendidos',
                            (string) ($summary['unique_clients'] ?? 0),
                            'bi-people',
                            'clientes únicos',
                            $comparisonHtml($comparison['unique_clients'] ?? null)
                        ) ?>

                        <?php if ($canViewFinancial): ?>
                            <?= $metricCard(
                                'Faturamento consolidado',
                                $reportMoney($summary['company_total'] ?? '0'),
                                'bi-cash-stack',
                                'cada OS contabilizada uma vez',
                                $comparisonHtml($comparison['company_total'] ?? null)
                            ) ?>

                            <?= $metricCard(
                                'Ticket médio',
                                $reportMoney($summary['average_ticket'] ?? '0'),
                                'bi-receipt',
                                'faturamento por OS'
                            ) ?>

                            <?= $metricCard(
                                'Total recebido',
                                $reportMoney($summary['received_total'] ?? '0'),
                                'bi-wallet2',
                                'estado atual das contas'
                            ) ?>

                            <?= $metricCard(
                                'Saldo a receber',
                                $reportMoney($summary['receivable_balance'] ?? '0'),
                                'bi-hourglass-split',
                                'sem contas canceladas'
                            ) ?>

                            <?= $metricCard(
                                'Contas pendentes',
                                (string) ($summary['pending_accounts'] ?? 0),
                                'bi-clock-history',
                                'pendentes, parciais ou vencidas'
                            ) ?>

                            <?= $metricCard(
                                'Contas vencidas',
                                (string) ($summary['overdue_accounts'] ?? 0),
                                'bi-exclamation-triangle',
                                'saldo maior que zero'
                            ) ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($canViewFinancial): ?>
                        <div class="report-breakdown-grid" aria-label="Composição financeira">
                            <div class="report-breakdown-item">
                                <span>Serviços</span>
                                <strong><?= h($reportMoney($summary['service_total'] ?? '0')) ?></strong>
                            </div>

                            <div class="report-breakdown-item">
                                <span>Produtos</span>
                                <strong><?= h($reportMoney($summary['product_total'] ?? '0')) ?></strong>
                            </div>

                            <div class="report-breakdown-item">
                                <span>Outros</span>
                                <strong><?= h($reportMoney($summary['other_total'] ?? '0')) ?></strong>
                            </div>

                            <div class="report-breakdown-item">
                                <span>Descontos</span>
                                <strong><?= h($reportMoney($summary['discount_total'] ?? '0')) ?></strong>
                            </div>

                            <div class="report-breakdown-item">
                                <span>Acréscimos</span>
                                <strong><?= h($reportMoney($summary['addition_total'] ?? '0')) ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="report-dashboard-grid">
                        <section class="report-subpanel" aria-labelledby="daily-evolution-title">
                            <h4 id="daily-evolution-title">Evolução diária</h4>
                            <p>
                                <?= $canViewFinancial
                                    ? 'Faturamento consolidado por data da finalização.'
                                    : 'Quantidade de OS finalizadas por dia.' ?>
                            </p>

                            <?php if ($dailyEvolution === []): ?>
                                <?php
                                empty_state(
                                    'Sem evolução no período',
                                    'Nenhuma OS finalizada foi encontrada.'
                                );
                                ?>
                            <?php else: ?>
                                <div class="report-evolution-scroll">
                                    <div class="report-evolution-chart">
                                        <?php foreach ($dailyEvolution as $day): ?>
                                            <?php
                                            if (!is_array($day)) {
                                                continue;
                                            }

                                            $dayValue = $canViewFinancial
                                                ? $decimalToCents($day['company_total'] ?? '0')
                                                : (int) ($day['orders'] ?? 0);

                                            $height = $maxDailyValue > 0
                                                ? max(
                                                    3,
                                                    min(
                                                        100,
                                                        intdiv(
                                                            $dayValue * 100,
                                                            $maxDailyValue
                                                        )
                                                    )
                                                )
                                                : 3;

                                            $dayDisplayValue = $canViewFinancial
                                                ? $reportMoney($day['company_total'] ?? '0')
                                                : (string) ($day['orders'] ?? 0) . ' OS';
                                            ?>

                                            <div
                                                class="report-evolution-day"
                                                title="<?= h(
                                                    (string) ($day['label'] ?? '')
                                                    . ' — '
                                                    . $dayDisplayValue
                                                ) ?>"
                                            >
                                                <div class="report-evolution-bar-wrap">
                                                    <span
                                                        class="report-evolution-bar"
                                                        style="height: <?= h((string) $height) ?>%"
                                                        aria-hidden="true"
                                                    ></span>
                                                </div>

                                                <strong><?= h((string) ($day['label'] ?? '')) ?></strong>
                                                <small><?= h((string) ($day['orders'] ?? 0)) ?> OS</small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>

                        <div class="d-grid gap-3">
                            <?php if ($canViewFinancial): ?>
                                <?php
                                $clientRanking = is_array($rankings['clients_by_revenue'] ?? null)
                                    ? $rankings['clients_by_revenue']
                                    : [];
                                ?>

                                <section class="report-subpanel" aria-labelledby="client-ranking-title">
                                    <h4 id="client-ranking-title">Clientes por faturamento</h4>
                                    <p>Os 10 clientes com maior valor executado.</p>

                                    <?php if ($clientRanking === []): ?>
                                        <span class="section-note">Nenhum cliente no período.</span>
                                    <?php else: ?>
                                        <div class="report-ranking-list">
                                            <?php foreach ($clientRanking as $index => $item): ?>
                                                <?php $item = is_array($item) ? $item : []; ?>

                                                <div class="report-ranking-item">
                                                    <div>
                                                        <strong>
                                                            <?= h(
                                                                ((int) $index + 1)
                                                                . '. '
                                                                . (string) ($item['name'] ?? 'Cliente')
                                                            ) ?>
                                                        </strong>
                                                        <span>
                                                            <?= h((string) ($item['orders'] ?? 0)) ?> OS
                                                        </span>
                                                    </div>

                                                    <div class="report-ranking-value">
                                                        <?= h($reportMoney($item['revenue_total'] ?? '0')) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            <?php endif; ?>

                            <?php
                            $serviceQuantityRanking = is_array($rankings['services_by_quantity'] ?? null)
                                ? $rankings['services_by_quantity']
                                : [];
                            ?>

                            <section class="report-subpanel" aria-labelledby="service-ranking-title">
                                <h4 id="service-ranking-title">Serviços mais executados</h4>
                                <p>Ranking por quantidade registrada nas finalizações.</p>

                                <?php if ($serviceQuantityRanking === []): ?>
                                    <span class="section-note">Nenhum serviço no período.</span>
                                <?php else: ?>
                                    <div class="report-ranking-list">
                                        <?php foreach ($serviceQuantityRanking as $index => $item): ?>
                                            <?php $item = is_array($item) ? $item : []; ?>

                                            <div class="report-ranking-item">
                                                <div>
                                                    <strong>
                                                        <?= h(
                                                            ((int) $index + 1)
                                                            . '. '
                                                            . (string) ($item['description'] ?? 'Serviço')
                                                        ) ?>
                                                    </strong>
                                                    <span>
                                                        <?= h((string) ($item['orders'] ?? 0)) ?> OS
                                                    </span>
                                                </div>

                                                <div class="report-ranking-value">
                                                    <?= h($reportDecimal($item['quantity_total'] ?? '0', 3)) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="report-accordion" id="report-sections-accordion">
        <?php if ($canViewClientReport): ?>
            <section class="report-accordion-item" data-report-print-target="clientes">
                <h2 class="m-0" id="report-clients-heading">
                    <button
                        class="report-accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#report-clients-collapse"
                        aria-expanded="false"
                        aria-controls="report-clients-collapse"
                    >
                        <span>
                            <i class="bi bi-person-vcard" aria-hidden="true"></i>
                            <span>
                                Relatório por cliente
                                <small>Faturamento, quantidade de OS, ticket e saldo por cliente.</small>
                            </span>
                        </span>

                        <i class="bi bi-chevron-down report-accordion-chevron" aria-hidden="true"></i>
                    </button>
                </h2>

                <div
                    class="collapse"
                    id="report-clients-collapse"
                    aria-labelledby="report-clients-heading"
                    data-report-section
                    data-section="clientes"
                >
                    <div class="report-accordion-content">
                        <div class="report-section-actions justify-content-end py-3">
                            <a
                                class="btn-filter btn-filter-ghost"
                                href="actions/relatorio-exportar.php?secao=clientes"
                                data-report-export-section
                            >
                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                Exportar
                            </a>

                            <button
                                class="btn-filter btn-filter-ghost"
                                type="button"
                                data-report-print-section="clientes"
                            >
                                <i class="bi bi-printer"></i>
                                Imprimir
                            </button>
                        </div>

                        <div data-report-section-content>
                            <div class="report-section-state">
                                <i class="bi bi-hand-index" aria-hidden="true"></i>
                                <strong>Abra esta seção para carregar os clientes</strong>
                                <p>Os dados serão consultados somente quando necessários.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($canViewServiceReport): ?>
            <section class="report-accordion-item" data-report-print-target="servicos">
                <h2 class="m-0" id="report-services-heading">
                    <button
                        class="report-accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#report-services-collapse"
                        aria-expanded="false"
                        aria-controls="report-services-collapse"
                    >
                        <span>
                            <i class="bi bi-tools" aria-hidden="true"></i>
                            <span>
                                Relatório por serviço
                                <small>Quantidade executada, OS, clientes e descrição histórica.</small>
                            </span>
                        </span>

                        <i class="bi bi-chevron-down report-accordion-chevron" aria-hidden="true"></i>
                    </button>
                </h2>

                <div
                    class="collapse"
                    id="report-services-collapse"
                    aria-labelledby="report-services-heading"
                    data-report-section
                    data-section="servicos"
                >
                    <div class="report-accordion-content">
                        <div class="report-section-actions justify-content-end py-3">
                            <a
                                class="btn-filter btn-filter-ghost"
                                href="actions/relatorio-exportar.php?secao=servicos"
                                data-report-export-section
                            >
                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                Exportar
                            </a>

                            <button
                                class="btn-filter btn-filter-ghost"
                                type="button"
                                data-report-print-section="servicos"
                            >
                                <i class="bi bi-printer"></i>
                                Imprimir
                            </button>
                        </div>

                        <div data-report-section-content>
                            <div class="report-section-state">
                                <i class="bi bi-hand-index" aria-hidden="true"></i>
                                <strong>Abra esta seção para carregar os serviços</strong>
                                <p>Os dados serão consultados somente quando necessários.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($canViewTeamReport): ?>
            <section class="report-accordion-item" data-report-print-target="equipe">
                <h2 class="m-0" id="report-team-heading">
                    <button
                        class="report-accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#report-team-collapse"
                        aria-expanded="false"
                        aria-controls="report-team-collapse"
                    >
                        <span>
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <span>
                                Equipe, metas e comissão
                                <small>Produção individual, metas, progresso e prêmio estimado.</small>
                            </span>
                        </span>

                        <i class="bi bi-chevron-down report-accordion-chevron" aria-hidden="true"></i>
                    </button>
                </h2>

                <div
                    class="collapse"
                    id="report-team-collapse"
                    aria-labelledby="report-team-heading"
                    data-report-section
                    data-section="equipe"
                >
                    <div class="report-accordion-content">
                        <div class="report-section-actions justify-content-end py-3">
                            <a
                                class="btn-filter btn-filter-ghost"
                                href="actions/relatorio-exportar.php?secao=equipe"
                                data-report-export-section
                            >
                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                Exportar
                            </a>

                            <button
                                class="btn-filter btn-filter-ghost"
                                type="button"
                                data-report-print-section="equipe"
                            >
                                <i class="bi bi-printer"></i>
                                Imprimir
                            </button>
                        </div>

                        <div data-report-section-content>
                            <div class="report-section-state">
                                <i class="bi bi-hand-index" aria-hidden="true"></i>
                                <strong>Abra esta seção para carregar a equipe</strong>
                                <p>A produção detalhada será consultada somente quando necessária.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<div
    class="modal fade"
    id="report-detail-modal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content visual-modal">
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        data-report-detail-title
                    >
                        Detalhamento do relatório
                    </h2>

                    <p class="text-muted small mb-0">
                        Registros considerados no período selecionado.
                    </p>
                </div>

                <button
                    class="btn-close"
                    type="button"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>
            </div>

            <div class="modal-body" data-report-detail-body>
                <div class="report-section-state">
                    <i class="bi bi-list-check" aria-hidden="true"></i>
                    <strong>Selecione um registro</strong>
                    <p>O detalhamento será exibido aqui.</p>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($canConfigureGoal): ?>
    <div
        class="modal fade"
        id="modal-configurar-meta"
        tabindex="-1"
        aria-hidden="true"
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
                        <h2 class="modal-title fs-5">
                            Configurar meta mensal
                        </h2>

                        <p class="text-muted small mb-0">
                            A configuração será aplicada aos funcionários no mês selecionado.
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
                        <i class="bi bi-info-circle me-1"></i>
                        Ao atingir a meta, o percentual é aplicado sobre todo o valor creditado ao funcionário, e não apenas sobre o excedente.
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="goal-competence">
                                Mês de referência
                            </label>

                            <input
                                class="form-control-os"
                                id="goal-competence"
                                type="month"
                                name="competencia"
                                min="2000-01"
                                max="2100-12"
                                value="<?= h($goalCompetence) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="goal-amount">
                                Valor da meta
                            </label>

                            <input
                                class="form-control-os"
                                id="goal-amount"
                                name="valor_meta"
                                inputmode="decimal"
                                maxlength="20"
                                placeholder="11.000,00"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="goal-percentage">
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

                    <button class="btn-modal-save" type="submit">
                        <i class="bi bi-check-lg"></i>
                        Salvar meta
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>