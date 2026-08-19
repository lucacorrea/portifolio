<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/dashboard.php';

$dbReady = pe_db_ready() && pe_schema_ready() && pe_program_schema_ready() && pe_lotacao_table_ready(pe_db());

$overview = [
    'total' => 0,
    'contemplados' => 0,
    'revisao_pendente' => 0,
    'cpf_duplicado' => 0,
    'lotados' => 0,
    'nao_lotados' => 0,
    'revisar_lotacao' => 0,
    'parceiros_ativos' => 0,
    'vagas_abertas' => 0,
    'encaminhamentos_abertos' => 0,
    'bolsas_pagas' => 0,
    'bolsas_valor' => 0.0,
    'frequencia_media' => 0.0,
    'frequencia_competencia' => null,
    'bolsas_competencia' => null,
];
$reviewCounts = [];
$monthly = ['labels' => [], 'values' => []];
$statusCounts = [];
$lotacaoCounts = [];
$referralCounts = [];
$grantCounts = ['competencia' => null, 'series' => []];
$freqRanges = ['competencia' => null, 'media_geral' => 0.0, 'series' => []];
$topBairros = [];
$topPartners = [];
$health = [];
$pipeline = [];
$alerts = [];
$activity = [];

if ($dbReady) {
    try {
        $pdo = pe_db();
        $overview = pe_dashboard_overview($pdo);
        $reviewCounts = pe_dashboard_review_counts($pdo);
        $monthly = pe_dashboard_monthly($pdo, 8);
        $statusCounts = pe_dashboard_status_counts($pdo);
        $lotacaoCounts = pe_dashboard_lotacao_distribution($pdo);
        $referralCounts = pe_dashboard_referral_counts($pdo);
        $grantCounts = pe_dashboard_grant_counts($pdo);
        $freqRanges = pe_dashboard_frequency_ranges($pdo);
        $topBairros = pe_dashboard_top_bairros($pdo, 8);
        $topPartners = pe_dashboard_top_partners($pdo, 8);
        $health = pe_dashboard_health($pdo);
        $pipeline = pe_dashboard_pipeline($pdo);
        $alerts = pe_dashboard_alerts($pdo, 12);
        $activity = pe_recent_activity($pdo, 10);
    } catch (Throwable) {
        $dbReady = false;
    }
}

$frequenciaMeta = $overview['frequencia_competencia'] ? pe_dashboard_month_label((string) $overview['frequencia_competencia']) : 'Sem competência';
$bolsaMeta = $overview['bolsas_competencia'] ? pe_dashboard_month_label((string) $overview['bolsas_competencia']) : 'Sem competência';

$pageDefinition = [
    'title' => 'Painel',
    'description' => 'Visão consolidada, analítica e operacional dos candidatos, qualidade cadastral, lotações e execução do programa.',
    'demo' => false,
    'show_states' => false,
    'actions' => [
        ['label' => 'Novo candidato', 'icon' => 'person-plus', 'primary' => true, 'href' => 'primeiro-emprego/cadastro-candidato.php'],
        ['label' => 'Importar Excel', 'icon' => 'file-earmark-spreadsheet', 'href' => 'primeiro-emprego/importar-candidatos.php'],
        ['label' => 'Abrir lotações', 'icon' => 'diagram-3', 'href' => 'primeiro-emprego/lotacoes.php'],
    ],
    'stats' => [
        ['label' => 'Candidatos', 'value' => (string) $overview['total'], 'detail' => 'Base cadastrada', 'icon' => 'people'],
        ['label' => 'Com lotação ativa', 'value' => (string) $overview['lotados'], 'detail' => 'Vínculos ativos no programa', 'icon' => 'person-workspace'],
        ['label' => 'Sem lotação', 'value' => (string) $overview['nao_lotados'], 'detail' => 'Precisam de vinculação', 'icon' => 'person-dash'],
        ['label' => 'Revisão pendente', 'value' => (string) $overview['revisao_pendente'], 'detail' => 'Qualidade cadastral', 'icon' => 'exclamation-circle'],
        ['label' => 'Revisar lotação', 'value' => (string) $overview['revisar_lotacao'], 'detail' => 'Registros incoerentes', 'icon' => 'diagram-3'],
        ['label' => 'Vagas abertas', 'value' => (string) $overview['vagas_abertas'], 'detail' => 'Oportunidades disponíveis', 'icon' => 'briefcase'],
        ['label' => 'Bolsas pagas', 'value' => (string) $overview['bolsas_pagas'], 'detail' => 'Competência ' . $bolsaMeta, 'icon' => 'cash-coin'],
        ['label' => 'Frequência média', 'value' => number_format((float) $overview['frequencia_media'], 1, ',', '.') . '%', 'detail' => 'Competência ' . $frequenciaMeta, 'icon' => 'graph-up-arrow'],
    ],
    'modal' => ['title' => 'Resumo do painel'],
];

$chartPayload = [
    'monthly' => $monthly,
    'status' => $statusCounts,
    'lotacao' => $lotacaoCounts,
    'review' => [
        ['label' => 'Revisar CPF', 'value' => (int) ($reviewCounts['Revisar CPF'] ?? 0)],
        ['label' => 'Revisar Telefone', 'value' => (int) ($reviewCounts['Revisar Telefone'] ?? 0)],
        ['label' => 'Revisar nascimento', 'value' => (int) ($reviewCounts['Revisar Data de Nascimento'] ?? 0)],
        ['label' => 'Revisar Cadastro', 'value' => (int) ($reviewCounts['Revisar Cadastro'] ?? 0)],
    ],
    'referrals' => $referralCounts,
    'grants' => $grantCounts,
    'frequency' => $freqRanges,
    'bairros' => $topBairros,
    'partners' => $topPartners,
];

ob_start();
?>
<?php if (!$dbReady): ?>
    <section class="content-card pe-form-card">
        <div class="alert alert-warning mb-0">
            <strong>O painel analítico ainda não pôde ser carregado.</strong>
            Verifique se as tabelas do módulo estão atualizadas, especialmente <code>pe_lotacoes</code> e as tabelas do pacote operacional.
        </div>
    </section>
<?php else: ?>
    <section class="content-card pe-form-card pe-dashboard-panel">
        <div class="pe-form-header">
            <div>
                <div class="card-kicker">Leitura executiva</div>
                <h2>Panorama operacional do programa</h2>
                <p>O painel agora destaca pipeline, distribuição da base, lotações, revisão cadastral e alertas prioritários para ação.</p>
            </div>
        </div>

        <div class="pe-dashboard-summary-grid">
            <article class="pe-dashboard-summary-card">
                <div class="card-kicker">Pipeline do programa</div>
                <h3>Fluxo principal</h3>
                <div class="pe-pipeline-strip">
                    <?php foreach ($pipeline as $step): ?>
                        <div class="pe-pipeline-step">
                            <strong><?= pe_h((string) $step['value']) ?></strong>
                            <span><?= pe_h((string) $step['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="pe-dashboard-summary-card">
                <div class="card-kicker">Financeiro e frequência</div>
                <h3>Execução recente</h3>
                <ul class="pe-metric-list">
                    <li>
                        <span>Valor pago em bolsas</span>
                        <strong>R$ <?= pe_h(number_format((float) $overview['bolsas_valor'], 2, ',', '.')) ?></strong>
                        <small><?= pe_h($bolsaMeta) ?></small>
                    </li>
                    <li>
                        <span>Encaminhamentos em aberto</span>
                        <strong><?= pe_h((string) $overview['encaminhamentos_abertos']) ?></strong>
                        <small>Demandam retorno</small>
                    </li>
                    <li>
                        <span>Parceiros ativos</span>
                        <strong><?= pe_h((string) $overview['parceiros_ativos']) ?></strong>
                        <small>Rede do programa</small>
                    </li>
                </ul>
            </article>
        </div>
    </section>

    <section class="pe-dashboard-chart-grid mt-3">
        <article class="content-card pe-form-card pe-chart-card pe-chart-card--wide">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Evolução da base</div>
                    <h3>Novos candidatos por mês</h3>
                    <p>Leitura de entrada no programa nos últimos 8 meses.</p>
                </div>
            </div>
            <div class="pe-chart-wrap"><canvas id="peChartMonthly"></canvas></div>
        </article>

        <article class="content-card pe-form-card pe-chart-card">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Situação da base</div>
                    <h3>Status dos candidatos</h3>
                    <p>Mostra a distribuição atual da base do programa.</p>
                </div>
            </div>
            <div class="pe-chart-wrap pe-chart-wrap--donut"><canvas id="peChartStatus"></canvas></div>
        </article>

        <article class="content-card pe-form-card pe-chart-card">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Lotações</div>
                    <h3>Situação de lotação</h3>
                    <p>Diferencia lotados, não lotados, prontos para importar e casos de revisão.</p>
                </div>
            </div>
            <div class="pe-chart-wrap pe-chart-wrap--donut"><canvas id="peChartLotacao"></canvas></div>
        </article>

        <article class="content-card pe-form-card pe-chart-card">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Qualidade cadastral</div>
                    <h3>Pendências por tipo</h3>
                    <p>Ajuda a priorizar revisão de CPF, telefone, nascimento e cadastros múltiplos.</p>
                </div>
            </div>
            <div class="pe-chart-wrap"><canvas id="peChartReview"></canvas></div>
        </article>

        <article class="content-card pe-form-card pe-chart-card">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Território</div>
                    <h3>Top bairros</h3>
                    <p>Concentração de candidatos por bairro.</p>
                </div>
            </div>
            <div class="pe-chart-wrap"><canvas id="peChartBairros"></canvas></div>
        </article>

        <article class="content-card pe-form-card pe-chart-card">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Rede parceira</div>
                    <h3>Órgãos com mais lotados</h3>
                    <p>Mostra a distribuição atual de participantes por órgão ou instituição parceira.</p>
                </div>
            </div>
            <div class="pe-chart-wrap"><canvas id="peChartPartners"></canvas></div>
        </article>

        <article class="content-card pe-form-card pe-chart-card">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Seleção</div>
                    <h3>Encaminhamentos por status</h3>
                    <p>Visão rápida do andamento das oportunidades.</p>
                </div>
            </div>
            <div class="pe-chart-wrap pe-chart-wrap--donut"><canvas id="peChartReferrals"></canvas></div>
        </article>

        <article class="content-card pe-form-card pe-chart-card">
            <div class="pe-chart-card__head">
                <div>
                    <div class="card-kicker">Benefícios e assiduidade</div>
                    <h3>Bolsas e frequência</h3>
                    <p>Bolsas por status e faixas de frequência da competência mais recente.</p>
                </div>
                <div class="pe-inline-meta">
                    <span><?= pe_h($bolsaMeta) ?></span>
                    <span><?= pe_h($frequenciaMeta) ?></span>
                </div>
            </div>
            <div class="pe-chart-wrap pe-chart-wrap--split">
                <div><canvas id="peChartGrants"></canvas></div>
                <div><canvas id="peChartFrequency"></canvas></div>
            </div>
        </article>
    </section>

    <section class="pe-dashboard-info-grid mt-3">
        <article class="content-card pe-form-card pe-dashboard-info-card">
            <div class="pe-form-header">
                <div>
                    <div class="card-kicker">Saúde operacional</div>
                    <h2>Indicadores rápidos</h2>
                    <p>Itens que ajudam a acompanhar o funcionamento diário do módulo.</p>
                </div>
            </div>
            <div class="pe-health-grid">
                <?php foreach ($health as $item): ?>
                    <div class="pe-health-card pe-health-card--<?= pe_h((string) $item['tone']) ?>">
                        <strong><?= pe_h((string) $item['value']) ?></strong>
                        <span><?= pe_h((string) $item['label']) ?></span>
                        <small><?= pe_h((string) $item['detail']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="content-card pe-form-card pe-dashboard-info-card">
            <div class="pe-form-header">
                <div>
                    <div class="card-kicker">Prioridades</div>
                    <h2>Alertas para agir agora</h2>
                    <p>Registros com maior urgência operacional e cadastral.</p>
                </div>
            </div>
            <div class="pe-alert-list">
                <?php if ($alerts): ?>
                    <?php foreach ($alerts as $item): ?>
                        <?php
                            $alerta = (string) ($item['alerta'] ?? 'Acompanhar');
                            $tone = 'warning';
                            if (stripos($alerta, 'CPF duplicado') !== false) {
                                $tone = 'danger';
                            } elseif (stripos($alerta, 'múltiplas pendências') !== false) {
                                $tone = 'danger';
                            } elseif (stripos($alerta, 'Revisar') !== false) {
                                $tone = 'warning';
                            } elseif (stripos($alerta, 'Sem lotação') !== false) {
                                $tone = 'info';
                            }
                        ?>
                        <article class="pe-alert-item">
                            <div>
                                <strong><?= pe_h((string) $item['nome']) ?></strong>
                                <span><?= pe_h(pe_format_cpf((string) ($item['cpf_exibicao'] ?? ''))) ?> · <?= pe_h((string) $item['bairro']) ?></span>
                            </div>
                            <span class="pe-inline-badge pe-inline-badge--<?= pe_h($tone) ?>"><?= pe_h($alerta) ?></span>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-light mb-0">Nenhum alerta prioritário encontrado no momento.</div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <?php if ($activity): ?>
        <section class="content-card pe-form-card mt-3">
            <div class="pe-form-header">
                <div>
                    <div class="card-kicker">Movimentações</div>
                    <h2>Atividade recente</h2>
                    <p>Últimos cadastros, importações e visitas registradas no módulo.</p>
                </div>
            </div>
            <div class="pe-activity-list">
                <?php foreach ($activity as $item): ?>
                    <article>
                        <i class="bi bi-clock-history"></i>
                        <div>
                            <strong><?= pe_h((string) $item['titulo']) ?></strong>
                            <span><?= pe_h((string) $item['descricao']) ?></span>
                        </div>
                        <time><?= pe_h(date('d/m/Y H:i', strtotime((string) $item['data_evento']))) ?></time>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <script id="peDashboardPayload" type="application/json"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script>
    (function () {
        const payloadEl = document.getElementById('peDashboardPayload');
        if (!payloadEl) return;

        const payload = JSON.parse(payloadEl.textContent || '{}');

        const boot = () => {
            if (!window.Chart) return;

            const palette = ['#176b85', '#3e8ea7', '#7ab6c8', '#b2d7e2', '#e3f1f5', '#f4a259', '#de6b48', '#7c4dff', '#2e7d32'];
            const warmPalette = ['#176b85', '#3e8ea7', '#f4a259', '#de6b48', '#7c4dff', '#2e7d32'];
            Chart.defaults.font.family = 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            Chart.defaults.color = '#49606b';
            Chart.defaults.plugins.legend.position = 'bottom';
            Chart.defaults.plugins.legend.labels.boxWidth = 14;
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.maintainAspectRatio = false;

            const normalizeSeries = series => Array.isArray(series) ? series.filter(item => Number(item.value || 0) > 0) : [];

            const renderEmptyState = canvasId => {
                const canvas = document.getElementById(canvasId);
                if (!canvas || !canvas.parentElement) return;
                canvas.parentElement.innerHTML = '<div class="pe-chart-empty">Sem dados suficientes para exibir este gráfico.</div>';
            };

            const create = (canvasId, configBuilder) => {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const config = configBuilder();
                if (!config) {
                    renderEmptyState(canvasId);
                    return;
                }
                new Chart(canvas, config);
            };

            create('peChartMonthly', () => {
                const labels = payload.monthly?.labels || [];
                const values = payload.monthly?.values || [];
                if (!labels.length || !values.some(value => Number(value) > 0)) return null;
                return {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Novos candidatos',
                            data: values,
                            borderColor: '#176b85',
                            backgroundColor: 'rgba(23, 107, 133, 0.12)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                };
            });

            create('peChartStatus', () => {
                const series = normalizeSeries(payload.status);
                if (!series.length) return null;
                return {
                    type: 'doughnut',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ data: series.map(item => Number(item.value || 0)), backgroundColor: palette }]
                    }
                };
            });

            create('peChartLotacao', () => {
                const series = normalizeSeries(payload.lotacao);
                if (!series.length) return null;
                return {
                    type: 'doughnut',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ data: series.map(item => Number(item.value || 0)), backgroundColor: ['#176b85', '#3e8ea7', '#f4a259', '#de6b48'] }]
                    }
                };
            });

            create('peChartReview', () => {
                const series = normalizeSeries(payload.review);
                if (!series.length) return null;
                return {
                    type: 'bar',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ label: 'Pendências', data: series.map(item => Number(item.value || 0)), backgroundColor: warmPalette }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                };
            });

            create('peChartBairros', () => {
                const series = normalizeSeries(payload.bairros);
                if (!series.length) return null;
                return {
                    type: 'bar',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ label: 'Candidatos', data: series.map(item => Number(item.value || 0)), backgroundColor: '#3e8ea7' }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                };
            });

            create('peChartPartners', () => {
                const series = normalizeSeries(payload.partners);
                if (!series.length) return null;
                return {
                    type: 'bar',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ label: 'Lotados', data: series.map(item => Number(item.value || 0)), backgroundColor: '#176b85' }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                };
            });

            create('peChartReferrals', () => {
                const series = normalizeSeries(payload.referrals);
                if (!series.length) return null;
                return {
                    type: 'doughnut',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ data: series.map(item => Number(item.value || 0)), backgroundColor: warmPalette }]
                    }
                };
            });

            create('peChartGrants', () => {
                const series = normalizeSeries(payload.grants?.series);
                if (!series.length) return null;
                return {
                    type: 'doughnut',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ data: series.map(item => Number(item.value || 0)), backgroundColor: ['#176b85', '#3e8ea7', '#f4a259', '#de6b48', '#7c4dff'] }]
                    }
                };
            });

            create('peChartFrequency', () => {
                const series = normalizeSeries(payload.frequency?.series);
                if (!series.length) return null;
                return {
                    type: 'bar',
                    data: {
                        labels: series.map(item => item.label),
                        datasets: [{ label: 'Participantes', data: series.map(item => Number(item.value || 0)), backgroundColor: ['#2e7d32', '#176b85', '#f4a259', '#de6b48'] }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                };
            });
        };

        if (window.Chart) {
            boot();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js';
        script.onload = boot;
        document.head.appendChild(script);
    })();
    </script>
<?php endif; ?>
<?php
$pageCustomContent = (string) ob_get_clean();
