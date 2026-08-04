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

$availableSections = [];

if ($canViewCompanyReport) {
    $availableSections[] = 'empresa';
}

if ($canViewClientReport) {
    $availableSections[] = 'clientes';
}

if ($canViewServiceReport) {
    $availableSections[] = 'servicos';
}

if ($canViewTeamReport) {
    $availableSections[] = 'equipe';
}

/*
 * =========================================================
 * PERÍODO GLOBAL
 * =========================================================
 */
$periodError = null;

try {
    $period = $application
        ->reports()
        ->resolvePeriod($_GET);
} catch (InvalidArgumentException $exception) {
    $periodError = $exception->getMessage();

    $period = $application
        ->reports()
        ->resolvePeriod([
            'modo' => 'mes',
            'competencia' => date('Y-m'),
        ]);
}

$periodMode = (string) (
    $period['mode']
    ?? 'month'
);

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

/*
 * =========================================================
 * RELATÓRIO ATIVO
 * =========================================================
 */
$requestedSection = strtolower(
    trim((string) ($_GET['secao'] ?? ''))
);

$activeSection = in_array(
    $requestedSection,
    $availableSections,
    true
)
    ? $requestedSection
    : ($availableSections[0] ?? '');

$sectionDefinitions = [
    'empresa' => [
        'title' => 'Visão geral da empresa',
        'description' => 'Indicadores consolidados, evolução e rankings.',
        'icon' => 'bi-building',
    ],

    'clientes' => [
        'title' => 'Relatório por cliente',
        'description' => 'Produção, faturamento e histórico de OS por cliente.',
        'icon' => 'bi-people',
    ],

    'servicos' => [
        'title' => 'Relatório por serviço',
        'description' => 'Serviços executados, quantidade e faturamento.',
        'icon' => 'bi-tools',
    ],

    'equipe' => [
        'title' => 'Equipe, metas e comissão',
        'description' => 'Produção individual, metas e prêmio estimado.',
        'icon' => 'bi-person-workspace',
    ],
];

$activeDefinition = $sectionDefinitions[$activeSection] ?? [
    'title' => 'Relatórios',
    'description' => 'Selecione um relatório disponível.',
    'icon' => 'bi-bar-chart',
];

$goalCompetence = $periodMode === 'month'
    ? $periodCompetence
    : date('Y-m');
?>

<style>
.reports-page {
    --report-border: #e2e8f0;
    --report-muted: #64748b;
    --report-soft: #f8fafc;
    --report-primary: #2563eb;
    --report-primary-soft: #eff6ff;
    --report-text: #0f172a;
}

.report-filter-panel,
.report-selector-card,
.report-workspace {
    border: 1px solid var(--report-border);
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.045);
}

.report-filter-panel,
.report-workspace {
    border-radius: 16px;
}

.report-filter-panel {
    padding: 18px;
    margin-bottom: 18px;
}

.report-filter-heading,
.report-workspace-header,
.report-period-summary {
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
.report-workspace-title h2 {
    margin: 0;
    color: var(--report-text);
    font-size: 1rem;
    font-weight: 700;
}

.report-filter-heading p,
.report-workspace-title p,
.report-period-summary p {
    margin: 4px 0 0;
    color: var(--report-muted);
    font-size: 0.84rem;
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
    font-size: 0.875rem;
    font-weight: 600;
}

.report-mode-selector input:checked + span {
    background: #ffffff;
    color: var(--report-primary);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
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

.report-filter-actions,
.report-workspace-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.report-period-summary {
    padding: 13px 15px;
    margin-bottom: 18px;
    border: 1px solid #dbeafe;
    border-radius: 13px;
    background: var(--report-primary-soft);
}

.report-period-summary strong {
    color: #1d4ed8;
}

.report-selector {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.report-selector-card {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
    padding: 16px;
    border-radius: 14px;
    color: inherit;
    text-align: left;
    cursor: pointer;
    transition:
        border-color 0.18s ease,
        transform 0.18s ease,
        box-shadow 0.18s ease;
}

.report-selector-card:hover,
.report-selector-card:focus-visible {
    border-color: #93c5fd;
    transform: translateY(-1px);
    outline: none;
}

.report-selector-card.is-active {
    border-color: var(--report-primary);
    background: var(--report-primary-soft);
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.12);
}

.report-selector-icon {
    display: inline-grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border-radius: 12px;
    background: #f1f5f9;
    color: #334155;
    font-size: 1.05rem;
}

.report-selector-card.is-active .report-selector-icon {
    background: #dbeafe;
    color: #1d4ed8;
}

.report-selector-copy {
    min-width: 0;
}

.report-selector-copy strong,
.report-selector-copy small {
    display: block;
}

.report-selector-copy strong {
    color: var(--report-text);
    font-size: 0.9rem;
}

.report-selector-copy small {
    margin-top: 3px;
    color: var(--report-muted);
    font-size: 0.73rem;
    line-height: 1.35;
}

.report-workspace {
    overflow: hidden;
}

.report-workspace-header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--report-border);
}

.report-workspace-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.report-workspace-icon {
    display: inline-grid;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    place-items: center;
    border-radius: 11px;
    background: #f1f5f9;
    color: #334155;
}

.report-workspace-content {
    min-height: 300px;
    padding: 18px;
}

.report-section-state {
    display: grid;
    justify-items: center;
    gap: 8px;
    padding: 48px 18px;
    color: var(--report-muted);
    text-align: center;
}

.report-section-state > i {
    font-size: 1.55rem;
}

.report-section-state strong {
    color: #334155;
}

.report-section-state p {
    max-width: 520px;
    margin: 0;
    font-size: 0.85rem;
}

.report-loading-icon {
    animation: report-spin 0.85s linear infinite;
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
    background: #ffffff;
}

.report-metric-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.report-metric-label {
    color: var(--report-muted);
    font-size: 0.82rem;
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
    color: var(--report-text);
    font-size: clamp(1.25rem, 2vw, 1.75rem);
    line-height: 1.1;
    overflow-wrap: anywhere;
}

.report-metric-note,
.report-comparison {
    display: block;
    margin-top: 7px;
    font-size: 0.75rem;
}

.report-comparison.is-positive {
    color: #15803d;
}

.report-comparison.is-negative {
    color: #b91c1c;
}

.report-comparison.is-neutral,
.report-metric-note {
    color: var(--report-muted);
}

.report-dashboard-grid {
    display: grid;
    grid-template-columns:
        minmax(0, 1.5fr)
        minmax(290px, 0.8fr);
    gap: 16px;
    margin-top: 16px;
}

.report-subpanel {
    min-width: 0;
    padding: 16px;
    border: 1px solid var(--report-border);
    border-radius: 14px;
    background: #ffffff;
}

.report-subpanel h3,
.report-subpanel h4 {
    margin: 0 0 5px;
    color: var(--report-text);
    font-size: 0.95rem;
    font-weight: 700;
}

.report-subpanel > p {
    margin: 0 0 14px;
    color: var(--report-muted);
    font-size: 0.8rem;
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

.report-breakdown-item span {
    display: block;
    color: var(--report-muted);
    font-size: 0.78rem;
}

.report-breakdown-item strong {
    display: block;
    margin-top: 5px;
    color: var(--report-text);
}

.report-ranking-list {
    display: grid;
    gap: 9px;
}

.report-ranking-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: var(--report-soft);
}

.report-ranking-item span {
    min-width: 0;
}

.report-ranking-item strong {
    color: var(--report-text);
    font-size: 0.82rem;
    text-align: right;
}

.report-ranking-item small {
    display: block;
    color: var(--report-muted);
    font-size: 0.72rem;
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
    font-size: 0.68rem;
    white-space: nowrap;
}

.report-evolution-day small {
    color: var(--report-muted);
    font-size: 0.65rem;
}

.report-detail-modal .modal-dialog {
    max-width: min(1180px, calc(100vw - 28px));
}

.report-detail-modal .modal-body {
    max-height: calc(100dvh - 180px);
    overflow: auto;
}

@keyframes report-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 1100px) {
    .report-selector,
    .report-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-breakdown-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 850px) {
    .report-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .report-filter-panel,
    .report-workspace-content {
        padding: 14px;
    }

    .report-selector {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 4px;
        scroll-snap-type: x proximity;
    }

    .report-selector-card {
        min-width: 250px;
        scroll-snap-align: start;
    }

    .report-filter-fields,
    .report-filter-actions,
    .report-workspace-actions {
        width: 100%;
    }

    .report-filter-actions .btn-filter,
    .report-workspace-actions .btn-filter {
        flex: 1 1 140px;
        justify-content: center;
    }

    .report-workspace-header {
        align-items: flex-start;
    }

    .report-metric-grid,
    .report-breakdown-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .os-sidebar,
    .os-topbar,
    .flash-stack,
    .report-filter-panel,
    .report-selector,
    .report-workspace-actions,
    .modal,
    .btn,
    .btn-filter {
        display: none !important;
    }

    .os-wrapper,
    .os-main,
    .page-body,
    .reports-page,
    .report-workspace,
    .report-workspace-content {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
    }
}
</style>

<div
    class="page-body reports-page"
    data-report-page
    data-section-endpoint="actions/relatorio-secao-carregar.php"
    data-initial-section="<?= h($activeSection) ?>"
>
    <section class="report-filter-panel">
        <div class="report-filter-heading">
            <div>
                <h2>
                    <i
                        class="bi bi-calendar3 me-1"
                        aria-hidden="true"
                    ></i>
                    Período do relatório
                </h2>

                <p>
                    O período será aplicado ao relatório selecionado.
                </p>
            </div>

            <div
                class="report-mode-selector"
                aria-label="Modo do período"
            >
                <label>
                    <input
                        type="radio"
                        name="report_mode_visual"
                        value="mes"
                        <?= $periodMode === 'month' ? 'checked' : '' ?>
                        data-report-mode-control
                    >

                    <span>
                        <i
                            class="bi bi-calendar-month"
                            aria-hidden="true"
                        ></i>
                        Mensal
                    </span>
                </label>

                <label>
                    <input
                        type="radio"
                        name="report_mode_visual"
                        value="periodo"
                        <?= $periodMode === 'custom' ? 'checked' : '' ?>
                        data-report-mode-control
                    >

                    <span>
                        <i
                            class="bi bi-calendar-range"
                            aria-hidden="true"
                        ></i>
                        Personalizado
                    </span>
                </label>
            </div>
        </div>

        <form
            method="get"
            action="relatorios.php"
            data-report-filter-form
            autocomplete="off"
        >
            <input
                type="hidden"
                name="secao"
                value="<?= h($activeSection) ?>"
                data-report-section-field
            >

            <input
                type="hidden"
                name="modo"
                value="<?= $periodMode === 'custom' ? 'periodo' : 'mes' ?>"
                data-report-mode-field
            >

            <div class="report-filter-fields">
                <div
                    class="form-group"
                    data-period-month-fields
                    <?= $periodMode === 'custom' ? 'hidden' : '' ?>
                >
                    <label
                        class="form-label"
                        for="report-competence"
                    >
                        Mês de referência
                    </label>

                    <input
                        class="form-control-os"
                        id="report-competence"
                        type="month"
                        name="competencia"
                        value="<?= h($periodCompetence) ?>"
                        <?= $periodMode === 'custom' ? 'disabled' : '' ?>
                        required
                    >
                </div>

                <div
                    class="form-group"
                    data-period-custom-fields
                    <?= $periodMode === 'month' ? 'hidden' : '' ?>
                >
                    <label
                        class="form-label"
                        for="report-start-date"
                    >
                        Data inicial
                    </label>

                    <input
                        class="form-control-os"
                        id="report-start-date"
                        type="date"
                        name="data_inicial"
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
                    <label
                        class="form-label"
                        for="report-end-date"
                    >
                        Data final
                    </label>

                    <input
                        class="form-control-os"
                        id="report-end-date"
                        type="date"
                        name="data_final"
                        value="<?= h($periodEnd) ?>"
                        <?= $periodMode === 'month' ? 'disabled' : '' ?>
                        required
                    >
                </div>

                <div class="report-filter-actions">
                    <button
                        class="btn-filter btn-filter-primary"
                        type="submit"
                    >
                        <i
                            class="bi bi-funnel"
                            aria-hidden="true"
                        ></i>
                        Aplicar período
                    </button>

                    <?php if ($canConfigureGoal): ?>
                        <button
                            class="btn-filter btn-filter-ghost"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#modal-configurar-meta"
                        >
                            <i
                                class="bi bi-bullseye"
                                aria-hidden="true"
                            ></i>
                            Configurar meta
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </section>

    <?php if ($periodError !== null): ?>
        <div class="alert alert-warning" role="alert">
            <?= h($periodError) ?>

            Foi aplicado o mês atual como período seguro.
        </div>
    <?php endif; ?>

    <div
        class="report-period-summary"
        role="status"
        aria-live="polite"
    >
        <div>
            <strong>Período aplicado:</strong>
            <?= h($periodLabel) ?>
        </div>

        <p class="mb-0">
            Clique em um relatório para preencher o painel abaixo.
        </p>
    </div>

    <?php if ($availableSections === []): ?>
        <?php
        empty_state(
            'Nenhum relatório disponível para este perfil',
            'Solicite uma permissão compatível com relatórios.'
        );
        ?>
    <?php else: ?>
        <nav
            class="report-selector"
            aria-label="Tipos de relatório"
        >
            <?php foreach ($availableSections as $sectionKey): ?>
                <?php
                $definition = $sectionDefinitions[$sectionKey];
                ?>

                <button
                    class="report-selector-card<?= $sectionKey === $activeSection ? ' is-active' : '' ?>"
                    type="button"
                    data-report-select="<?= h($sectionKey) ?>"
                    data-report-title="<?= h($definition['title']) ?>"
                    data-report-description="<?= h($definition['description']) ?>"
                    data-report-icon="<?= h($definition['icon']) ?>"
                    aria-pressed="<?= $sectionKey === $activeSection ? 'true' : 'false' ?>"
                >
                    <span class="report-selector-icon">
                        <i
                            class="bi <?= h($definition['icon']) ?>"
                            aria-hidden="true"
                        ></i>
                    </span>

                    <span class="report-selector-copy">
                        <strong>
                            <?= h($definition['title']) ?>
                        </strong>

                        <small>
                            <?= h($definition['description']) ?>
                        </small>
                    </span>
                </button>
            <?php endforeach; ?>
        </nav>

        <section class="report-workspace">
            <header class="report-workspace-header">
                <div class="report-workspace-title">
                    <span class="report-workspace-icon">
                        <i
                            class="bi <?= h($activeDefinition['icon']) ?>"
                            data-report-workspace-icon
                            aria-hidden="true"
                        ></i>
                    </span>

                    <div>
                        <h2 data-report-workspace-title>
                            <?= h($activeDefinition['title']) ?>
                        </h2>

                        <p data-report-workspace-description>
                            <?= h($activeDefinition['description']) ?>
                        </p>
                    </div>
                </div>

                <div class="report-workspace-actions">
                    <a
                        class="btn-filter btn-filter-ghost"
                        href="#"
                        data-report-export-current
                    >
                        <i
                            class="bi bi-file-earmark-spreadsheet"
                            aria-hidden="true"
                        ></i>
                        Exportar CSV
                    </a>

                    <button
                        class="btn-filter btn-filter-ghost"
                        type="button"
                        data-report-print-current
                    >
                        <i
                            class="bi bi-printer"
                            aria-hidden="true"
                        ></i>
                        Imprimir
                    </button>
                </div>
            </header>

            <div
                class="report-workspace-content"
                data-report-active-section
                data-report-section
                data-section="<?= h($activeSection) ?>"
                data-report-section-content
                aria-live="polite"
            >
                <div
                    class="report-section-state"
                    role="status"
                >
                    <i
                        class="bi bi-arrow-repeat report-loading-icon"
                        aria-hidden="true"
                    ></i>

                    <strong>Carregando relatório</strong>

                    <p>
                        Os dados do período selecionado estão sendo preparados.
                    </p>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<div
    class="modal fade report-detail-modal"
    id="report-detail-modal"
    tabindex="-1"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
    >
        <div class="modal-content visual-modal">
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        data-report-detail-title
                    >
                        Detalhamento
                    </h2>

                    <p class="text-muted small mb-0">
                        Registros vinculados ao período selecionado.
                    </p>
                </div>

                <button
                    class="btn-close"
                    type="button"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>
            </div>

            <div
                class="modal-body"
                data-report-detail-body
            >
                <div class="report-section-state">
                    <i
                        class="bi bi-list-check"
                        aria-hidden="true"
                    ></i>

                    <strong>Selecione um registro</strong>

                    <p>
                        O detalhamento será exibido aqui.
                    </p>
                </div>
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
                            A regra valerá para todos os funcionários no mês selecionado.
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
                        sobre todo o valor creditado ao funcionário.
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
                                value="<?= h($goalCompetence) ?>"
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
                                    required
                                >

                                <span class="input-group-text">
                                    %
                                </span>
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
                        <i
                            class="bi bi-check-lg"
                            aria-hidden="true"
                        ></i>
                        Salvar meta
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>