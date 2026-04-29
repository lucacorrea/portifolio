<?php
$paginaAtual = 'relatorios';
$paginaTitulo = 'Relatórios Gerenciais';
$paginaDescricao = 'Indicadores globais de protocolos, usuários e operação.';
$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário Demo';
$usuarioCargo = 'Dono';
$tituloPagina = 'Dono - Relatórios Gerenciais';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
| Mantidas simples para evitar erro em hospedagem.
*/
if (!function_exists('dono_h')) {
    function dono_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dono_money')) {
    function dono_money($value)
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('dono_int')) {
    function dono_int($value)
    {
        return number_format((float)$value, 0, ',', '.');
    }
}

if (!function_exists('dono_percent')) {
    function dono_percent($value)
    {
        $value = (int)$value;

        if ($value > 0) {
            return '+' . $value . '%';
        }

        return $value . '%';
    }
}

if (!function_exists('dono_trend_class')) {
    function dono_trend_class($value)
    {
        $value = (int)$value;

        if ($value > 0) {
            return 'up';
        }

        if ($value < 0) {
            return 'down';
        }

        return 'neutral';
    }
}

/*
|--------------------------------------------------------------------------
| Período fictício
|--------------------------------------------------------------------------
*/
$periodoSelecionado = $_GET['periodo'] ?? 'trimestre';

$periodosPermitidos = ['mes', 'trimestre', 'semestre', 'ano'];

if (!in_array($periodoSelecionado, $periodosPermitidos, true)) {
    $periodoSelecionado = 'trimestre';
}

$hoje = new DateTimeImmutable('now');

switch ($periodoSelecionado) {
    case 'mes':
        $periodo = [
            'valor' => 'mes',
            'label' => 'Este mês',
            'inicio' => $hoje->modify('first day of this month')->setTime(0, 0, 0),
            'fim' => $hoje->setTime(23, 59, 59),
        ];

        $indicadores = [
            'protocolos_total' => 118,
            'protocolos_concluidos' => 84,
            'protocolos_pendentes' => 16,
            'protocolos_atrasados' => 5,
            'protocolos_trend' => 12,

            'orcamentos_total' => 46,
            'orcamentos_finalizados' => 32,
            'orcamentos_pendentes' => 14,
            'orcamentos_trend' => 9,

            'valor_total_orcamentos' => 28750.00,
            'ticket_medio' => 898.44,

            'usuarios_total' => 12,
            'usuarios_ativos' => 9,
            'usuarios_inativos' => 3,
            'usuarios_ativos_percentual' => 75,
        ];

        $graficos = [
            'evolucao' => [
                'labels' => ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                'abertos' => [24, 31, 28, 35],
                'concluidos' => [18, 22, 20, 24],
                'pendentes' => [6, 9, 8, 10],
            ],
            'status' => [
                'labels' => ['Concluídos', 'Em análise', 'Pendentes'],
                'valores' => [84, 18, 16],
            ],
        ];
        break;

    case 'semestre':
        $periodo = [
            'valor' => 'semestre',
            'label' => 'Último semestre',
            'inicio' => $hoje->modify('first day of this month')->modify('-5 months')->setTime(0, 0, 0),
            'fim' => $hoje->setTime(23, 59, 59),
        ];

        $indicadores = [
            'protocolos_total' => 684,
            'protocolos_concluidos' => 512,
            'protocolos_pendentes' => 39,
            'protocolos_atrasados' => 11,
            'protocolos_trend' => 22,

            'orcamentos_total' => 212,
            'orcamentos_finalizados' => 168,
            'orcamentos_pendentes' => 44,
            'orcamentos_trend' => 17,

            'valor_total_orcamentos' => 148920.00,
            'ticket_medio' => 886.43,

            'usuarios_total' => 12,
            'usuarios_ativos' => 9,
            'usuarios_inativos' => 3,
            'usuarios_ativos_percentual' => 75,
        ];

        $graficos = [
            'evolucao' => [
                'labels' => ['Nov', 'Dez', 'Jan', 'Fev', 'Mar', 'Abr'],
                'abertos' => [92, 104, 111, 118, 126, 133],
                'concluidos' => [70, 81, 86, 91, 94, 90],
                'pendentes' => [12, 15, 19, 17, 20, 21],
            ],
            'status' => [
                'labels' => ['Concluídos', 'Em análise', 'Pendentes'],
                'valores' => [512, 133, 39],
            ],
        ];
        break;

    case 'ano':
        $periodo = [
            'valor' => 'ano',
            'label' => 'Este ano',
            'inicio' => $hoje->setDate((int)$hoje->format('Y'), 1, 1)->setTime(0, 0, 0),
            'fim' => $hoje->setTime(23, 59, 59),
        ];

        $indicadores = [
            'protocolos_total' => 1248,
            'protocolos_concluidos' => 936,
            'protocolos_pendentes' => 64,
            'protocolos_atrasados' => 18,
            'protocolos_trend' => 31,

            'orcamentos_total' => 398,
            'orcamentos_finalizados' => 301,
            'orcamentos_pendentes' => 97,
            'orcamentos_trend' => 24,

            'valor_total_orcamentos' => 276480.00,
            'ticket_medio' => 918.54,

            'usuarios_total' => 12,
            'usuarios_ativos' => 9,
            'usuarios_inativos' => 3,
            'usuarios_ativos_percentual' => 75,
        ];

        $graficos = [
            'evolucao' => [
                'labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                'abertos' => [88, 94, 101, 118, 125, 132, 137, 121, 111, 98, 92, 31],
                'concluidos' => [62, 70, 82, 89, 94, 98, 104, 96, 88, 75, 64, 14],
                'pendentes' => [12, 14, 16, 18, 17, 21, 19, 16, 14, 12, 9, 4],
            ],
            'status' => [
                'labels' => ['Concluídos', 'Em análise', 'Pendentes'],
                'valores' => [936, 248, 64],
            ],
        ];
        break;

    case 'trimestre':
    default:
        $periodo = [
            'valor' => 'trimestre',
            'label' => 'Último trimestre',
            'inicio' => $hoje->modify('first day of this month')->modify('-2 months')->setTime(0, 0, 0),
            'fim' => $hoje->setTime(23, 59, 59),
        ];

        $indicadores = [
            'protocolos_total' => 342,
            'protocolos_concluidos' => 247,
            'protocolos_pendentes' => 14,
            'protocolos_atrasados' => 6,
            'protocolos_trend' => 18,

            'orcamentos_total' => 112,
            'orcamentos_finalizados' => 89,
            'orcamentos_pendentes' => 23,
            'orcamentos_trend' => 11,

            'valor_total_orcamentos' => 48750.00,
            'ticket_medio' => 547.75,

            'usuarios_total' => 12,
            'usuarios_ativos' => 9,
            'usuarios_inativos' => 3,
            'usuarios_ativos_percentual' => 75,
        ];

        $graficos = [
            'evolucao' => [
                'labels' => ['Fev', 'Mar', 'Abr'],
                'abertos' => [104, 113, 125],
                'concluidos' => [72, 81, 94],
                'pendentes' => [13, 15, 14],
            ],
            'status' => [
                'labels' => ['Concluídos', 'Em análise', 'Pendentes'],
                'valores' => [247, 81, 14],
            ],
        ];
        break;
}

/*
|--------------------------------------------------------------------------
| Dados fictícios fixos
|--------------------------------------------------------------------------
*/
$relatorio = [
    'indicadores' => $indicadores,
    'graficos' => $graficos,

    'areas' => [
        [
            'area' => 'Recepção',
            'descricao' => 'Entrada e triagem',
            'volume' => '128 atendimentos',
            'concluidos' => '89 encaminhados',
            'pendencias' => '17',
            'eficiencia' => 69,
            'status' => 'progress',
            'leitura' => 'Operação estável',
        ],
        [
            'area' => 'Administrativo',
            'descricao' => 'Análise e orçamento',
            'volume' => '94 análises',
            'concluidos' => '72 finalizadas',
            'pendencias' => '11',
            'eficiencia' => 76,
            'status' => 'ok',
            'leitura' => 'Boa entrega',
        ],
        [
            'area' => 'Gestão',
            'descricao' => 'Usuários e permissões',
            'volume' => '12 usuários',
            'concluidos' => '9 ativos',
            'pendencias' => '3 revisões',
            'eficiencia' => 75,
            'status' => 'pending',
            'leitura' => 'Acompanhar acessos',
        ],
    ],

    'ranking' => [
        [
            'area' => 'Administrativo',
            'descricao' => 'Maior taxa de conclusão',
            'eficiencia' => 76,
        ],
        [
            'area' => 'Gestão',
            'descricao' => 'Equipe ativa e controlada',
            'eficiencia' => 75,
        ],
        [
            'area' => 'Recepção',
            'descricao' => 'Bom volume de atendimento',
            'eficiencia' => 69,
        ],
    ],

    'alertas' => [
        [
            'tipo' => 'danger',
            'titulo' => $indicadores['protocolos_atrasados'] . ' protocolos atrasados',
            'descricao' => 'Existem protocolos sem movimentação há mais de 7 dias. Recomenda-se revisar esses casos com prioridade.',
        ],
        [
            'tipo' => 'warning',
            'titulo' => $indicadores['protocolos_pendentes'] . ' pendências abertas',
            'descricao' => 'A operação está estável, mas as pendências precisam ser acompanhadas para evitar acúmulo.',
        ],
        [
            'tipo' => 'success',
            'titulo' => dono_money($indicadores['valor_total_orcamentos']) . ' em orçamentos',
            'descricao' => 'O volume financeiro do período está positivo e indica boa movimentação operacional.',
        ],
    ],

    'movimentos' => [
        [
            'titulo' => 'Orçamento aprovado',
            'descricao' => 'Administrativo finalizou uma nova análise com valor aprovado pelo cliente.',
        ],
        [
            'titulo' => 'Novo protocolo recebido',
            'descricao' => 'Recepção registrou atendimento prioritário para análise.',
        ],
        [
            'titulo' => 'Usuário inativo identificado',
            'descricao' => 'Gestão precisa revisar permissões e acessos antigos.',
        ],
    ],
];

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="owner-report-hero card">
            <div class="owner-report-hero-content">
                <span class="owner-report-tag">Visão executiva</span>

                <h1>Relatórios Gerenciais</h1>

                <p>
                    Acompanhe o desempenho geral da operação, identifique gargalos,
                    monitore produtividade por setor e tome decisões com mais segurança.
                </p>

                <div class="owner-report-actions">
                    <a href="#" class="btn-owner-primary" onclick="alert('Exportação PDF fictícia.'); return false;">
                        Exportar PDF
                    </a>

                    <a href="#" class="btn-owner-secondary" onclick="alert('Exportação Excel fictícia.'); return false;">
                        Exportar Excel
                    </a>
                </div>
            </div>

            <form method="GET" class="owner-report-filter">
                <input type="hidden" name="pagina" value="relatorios">

                <label for="periodoRelatorio">Período analisado</label>

                <select id="periodoRelatorio" name="periodo" onchange="this.form.submit()">
                    <option value="trimestre" <?= $periodo['valor'] === 'trimestre' ? 'selected' : '' ?>>
                        Último trimestre
                    </option>

                    <option value="mes" <?= $periodo['valor'] === 'mes' ? 'selected' : '' ?>>
                        Este mês
                    </option>

                    <option value="semestre" <?= $periodo['valor'] === 'semestre' ? 'selected' : '' ?>>
                        Último semestre
                    </option>

                    <option value="ano" <?= $periodo['valor'] === 'ano' ? 'selected' : '' ?>>
                        Este ano
                    </option>
                </select>

                <div class="owner-report-date">
                    Período:
                    <strong>
                        <?= dono_h($periodo['inicio']->format('d/m/Y')) ?>
                        até
                        <?= dono_h($periodo['fim']->format('d/m/Y')) ?>
                    </strong>
                </div>

                <div class="owner-report-date owner-report-updated">
                    Atualizado em
                    <strong><?= date('d/m/Y H:i') ?></strong>
                </div>
            </form>
        </section>

        <section class="stats-grid owner-kpi-grid">
            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">📂</div>

                    <span class="trend <?= dono_h(dono_trend_class($indicadores['protocolos_trend'])) ?>">
                        <?= dono_h(dono_percent($indicadores['protocolos_trend'])) ?>
                    </span>
                </div>

                <h3><?= dono_int($indicadores['protocolos_total']) ?></h3>
                <p>Protocolos no período</p>

                <div class="owner-kpi-footer">
                    <span>Concluídos</span>
                    <strong><?= dono_int($indicadores['protocolos_concluidos']) ?></strong>
                </div>

                <div class="owner-progress" style="--progress: 86%;">
                    <span></span>
                </div>
            </article>

            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">💰</div>

                    <span class="trend <?= dono_h(dono_trend_class($indicadores['orcamentos_trend'])) ?>">
                        <?= dono_h(dono_percent($indicadores['orcamentos_trend'])) ?>
                    </span>
                </div>

                <h3><?= dono_int($indicadores['orcamentos_finalizados']) ?></h3>
                <p>Orçamentos finalizados</p>

                <div class="owner-kpi-footer">
                    <span>Total no período</span>
                    <strong><?= dono_int($indicadores['orcamentos_total']) ?></strong>
                </div>

                <div class="owner-progress" style="--progress: 72%;">
                    <span></span>
                </div>
            </article>

            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">⏳</div>
                    <span class="trend warn">Atenção</span>
                </div>

                <h3><?= dono_int($indicadores['protocolos_pendentes']) ?></h3>
                <p>Pendências abertas</p>

                <div class="owner-kpi-footer">
                    <span>Atrasados</span>
                    <strong><?= dono_int($indicadores['protocolos_atrasados']) ?></strong>
                </div>

                <div class="owner-progress danger" style="--progress: 58%;">
                    <span></span>
                </div>
            </article>

            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-info">👤</div>

                    <span class="trend up">
                        <?= dono_int($indicadores['usuarios_ativos']) ?> ativos
                    </span>
                </div>

                <h3><?= dono_int($indicadores['usuarios_total']) ?></h3>
                <p>Usuários cadastrados</p>

                <div class="owner-kpi-footer">
                    <span>Equipe ativa</span>
                    <strong><?= dono_int($indicadores['usuarios_ativos_percentual']) ?>%</strong>
                </div>

                <div class="owner-progress" style="--progress: <?= (int)$indicadores['usuarios_ativos_percentual'] ?>%;">
                    <span></span>
                </div>
            </article>
        </section>

        <section class="stats-grid owner-kpi-grid owner-money-grid">
            <article class="card stat-card owner-kpi-card owner-money-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">💵</div>
                    <span class="trend up">Financeiro</span>
                </div>

                <h3><?= dono_money($indicadores['valor_total_orcamentos']) ?></h3>
                <p>Valor total em orçamentos finalizados</p>

                <div class="owner-kpi-footer">
                    <span>Ticket médio</span>
                    <strong><?= dono_money($indicadores['ticket_medio']) ?></strong>
                </div>
            </article>

            <article class="card stat-card owner-kpi-card owner-money-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">🚨</div>
                    <span class="trend warn">Controle</span>
                </div>

                <h3><?= dono_int($indicadores['protocolos_atrasados']) ?></h3>
                <p>Protocolos atrasados</p>

                <div class="owner-kpi-footer">
                    <span>Mais de 7 dias sem movimentação</span>
                    <strong>Revisar</strong>
                </div>
            </article>
        </section>

        <section class="owner-report-grid">
            <article class="card owner-chart-card owner-chart-large">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Evolução de protocolos</h2>
                        <p>Comparativo entre protocolos abertos, concluídos e pendentes.</p>
                    </div>

                    <span class="owner-chip success">
                        <?= dono_h($periodo['label']) ?>
                    </span>
                </div>

                <div class="owner-chart-box">
                    <canvas id="protocolosChart"></canvas>
                </div>
            </article>

            <article class="card owner-chart-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Status geral</h2>
                        <p>Distribuição atual da operação.</p>
                    </div>
                </div>

                <div class="owner-chart-box owner-chart-donut">
                    <canvas id="statusChart"></canvas>
                </div>

                <div class="owner-status-list">
                    <?php foreach ($relatorio['graficos']['status']['labels'] as $index => $label): ?>
                        <div>
                            <span class="dot <?= $index === 0 ? 'ok' : ($index === 1 ? 'warn' : 'danger') ?>"></span>
                            <?= dono_h($label) ?>
                            <strong><?= dono_int($relatorio['graficos']['status']['valores'][$index] ?? 0) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="owner-insights-grid">
            <article class="card owner-insight-card">
                <div class="owner-insight-icon success">✓</div>

                <div>
                    <h3>Melhor desempenho</h3>
                    <p>
                        O administrativo finalizou
                        <strong><?= dono_int($indicadores['orcamentos_finalizados']) ?></strong>
                        orçamento(s) no período selecionado.
                    </p>
                </div>
            </article>

            <article class="card owner-insight-card">
                <div class="owner-insight-icon warning">!</div>

                <div>
                    <h3>Ponto de atenção</h3>
                    <p>
                        Existem
                        <strong><?= dono_int($indicadores['protocolos_pendentes']) ?></strong>
                        pendência(s) aberta(s). O ideal é revisar os processos parados.
                    </p>
                </div>
            </article>

            <article class="card owner-insight-card">
                <div class="owner-insight-icon info">i</div>

                <div>
                    <h3>Leitura gerencial</h3>
                    <p>
                        O dono deve acompanhar atrasos, produtividade por área e valor de orçamentos finalizados.
                    </p>
                </div>
            </article>
        </section>

        <section class="card panel owner-table-panel">
            <div class="panel-header owner-panel-header">
                <div>
                    <h2>Resumo por área</h2>
                    <p>Relatório consolidado para acompanhamento do dono.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="owner-table">
                    <thead>
                        <tr>
                            <th>Área</th>
                            <th>Volume</th>
                            <th>Concluídos</th>
                            <th>Pendências</th>
                            <th>Eficiência</th>
                            <th>Leitura</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($relatorio['areas'] as $area): ?>
                            <tr>
                                <td>
                                    <strong><?= dono_h($area['area']) ?></strong>
                                    <small><?= dono_h($area['descricao']) ?></small>
                                </td>

                                <td><?= dono_h($area['volume']) ?></td>
                                <td><?= dono_h($area['concluidos']) ?></td>
                                <td><?= dono_h($area['pendencias']) ?></td>

                                <td>
                                    <div class="owner-mini-progress <?= $area['eficiencia'] < 50 ? 'danger' : '' ?>" style="--progress: <?= (int)$area['eficiencia'] ?>%;">
                                        <span></span>
                                    </div>

                                    <small><?= dono_int($area['eficiencia']) ?>%</small>
                                </td>

                                <td>
                                    <span class="status <?= dono_h($area['status']) ?>">
                                        <?= dono_h($area['leitura']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="owner-bottom-grid">
            <article class="card owner-ranking-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Ranking operacional</h2>
                        <p>Setores com maior desempenho no período.</p>
                    </div>
                </div>

                <div class="owner-ranking-list">
                    <?php foreach ($relatorio['ranking'] as $index => $item): ?>
                        <div class="owner-ranking-item">
                            <span class="rank-number"><?= $index + 1 ?></span>

                            <div>
                                <strong><?= dono_h($item['area']) ?></strong>
                                <small><?= dono_h($item['descricao']) ?></small>
                            </div>

                            <b><?= dono_int($item['eficiencia']) ?>%</b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card owner-alert-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Alertas do dono</h2>
                        <p>Itens que merecem acompanhamento.</p>
                    </div>
                </div>

                <div class="owner-alert-list">
                    <?php foreach ($relatorio['alertas'] as $alerta): ?>
                        <div class="owner-alert-item <?= dono_h($alerta['tipo']) ?>">
                            <strong><?= dono_h($alerta['titulo']) ?></strong>
                            <span><?= dono_h($alerta['descricao']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card owner-timeline-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Últimos movimentos</h2>
                        <p>Resumo recente da operação.</p>
                    </div>
                </div>

                <div class="owner-timeline">
                    <?php foreach ($relatorio['movimentos'] as $movimento): ?>
                        <div class="owner-timeline-item">
                            <span></span>

                            <div>
                                <strong><?= dono_h($movimento['titulo']) ?></strong>
                                <small><?= dono_h($movimento['descricao']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<script>
    window.relatorioDonoCharts = <?= json_encode($relatorio['graficos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const dados = window.relatorioDonoCharts || {};

    const protocolosCanvas = document.getElementById("protocolosChart");
    const statusCanvas = document.getElementById("statusChart");

    if (typeof Chart === "undefined") {
        console.warn("Chart.js não foi carregado.");
        return;
    }

    Chart.defaults.font.family = "'Inter', 'Segoe UI', Arial, sans-serif";
    Chart.defaults.color = "#475569";

    if (protocolosCanvas) {
        const evolucao = dados.evolucao || {
            labels: [],
            abertos: [],
            concluidos: [],
            pendentes: []
        };

        new Chart(protocolosCanvas, {
            type: "line",
            data: {
                labels: evolucao.labels || [],
                datasets: [
                    {
                        label: "Protocolos abertos",
                        data: evolucao.abertos || [],
                        borderColor: "#1b5e20",
                        backgroundColor: "rgba(27, 94, 32, 0.10)",
                        pointBackgroundColor: "#1b5e20",
                        pointBorderColor: "#ffffff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: "Concluídos",
                        data: evolucao.concluidos || [],
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37, 99, 235, 0.08)",
                        pointBackgroundColor: "#2563eb",
                        pointBorderColor: "#ffffff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: "Pendentes",
                        data: evolucao.pendentes || [],
                        borderColor: "#f59e0b",
                        backgroundColor: "rgba(245, 158, 11, 0.08)",
                        pointBackgroundColor: "#f59e0b",
                        pointBorderColor: "#ffffff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: "index",
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 18,
                            font: {
                                weight: "700"
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: "#102016",
                        titleColor: "#ffffff",
                        bodyColor: "#ffffff",
                        padding: 12,
                        cornerRadius: 12
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: "700"
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: "rgba(148, 163, 184, 0.18)"
                        }
                    }
                }
            }
        });
    }

    if (statusCanvas) {
        const status = dados.status || {
            labels: ["Concluídos", "Em análise", "Pendentes"],
            valores: [0, 0, 0]
        };

        new Chart(statusCanvas, {
            type: "doughnut",
            data: {
                labels: status.labels || [],
                datasets: [
                    {
                        data: status.valores || [],
                        backgroundColor: [
                            "#1b5e20",
                            "#2563eb",
                            "#f59e0b"
                        ],
                        borderColor: "#ffffff",
                        borderWidth: 5,
                        hoverOffset: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "68%",
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "#102016",
                        titleColor: "#ffffff",
                        bodyColor: "#ffffff",
                        padding: 12,
                        cornerRadius: 12
                    }
                }
            }
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>