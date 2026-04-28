<?php
$paginaAtual = 'relatorios';
$paginaTitulo = 'Relatórios';
$paginaDescricao = 'Indicadores gerenciais do administrativo, produtividade da equipe e acompanhamento dos orçamentos.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Exportar Relatório';
$linkBotaoAcao = '#';
$tituloPagina = 'Administrativo - Relatórios';
$cssPagina = 'assets/css/administrativo/styleAdministrativo.css';

$dadosMensais = [
    ['periodo' => 'Fevereiro/2026', 'recebidos' => 96,  'orcamentos' => 71, 'concluidos' => 58, 'pendencias' => 12, 'tempo_medio' => 21],
    ['periodo' => 'Março/2026',     'recebidos' => 108, 'orcamentos' => 82, 'concluidos' => 67, 'pendencias' => 15, 'tempo_medio' => 19],
    ['periodo' => 'Abril/2026',     'recebidos' => 126, 'orcamentos' => 94, 'concluidos' => 79, 'pendencias' => 18, 'tempo_medio' => 17],
];

$labelsGrafico        = array_column($dadosMensais, 'periodo');
$dadosRecebidos       = array_column($dadosMensais, 'recebidos');
$dadosOrcamentos      = array_column($dadosMensais, 'orcamentos');
$dadosConcluidos      = array_column($dadosMensais, 'concluidos');
$dadosPendencias      = array_column($dadosMensais, 'pendencias');
$dadosTempoMedio      = array_column($dadosMensais, 'tempo_medio');

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">📥</div>
                    <span class="trend up">+17%</span>
                </div>
                <h3>126</h3>
                <p>Protocolos recebidos no mês</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">💰</div>
                    <span class="trend up">+15%</span>
                </div>
                <h3>94</h3>
                <p>Orçamentos gerados</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-info">✅</div>
                    <span class="trend up">+18%</span>
                </div>
                <h3>79</h3>
                <p>Processos concluídos</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">⏱️</div>
                    <span class="trend up">-2 min</span>
                </div>
                <h3>17 min</h3>
                <p>Tempo médio de análise</p>
            </article>
        </section>

        <section class="main-grid relatorios-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Volume operacional</h2>
                        <p>Comparativo entre protocolos recebidos, orçamentos gerados e processos concluídos.</p>
                    </div>
                </div>

                <div class="chart-box">
                    <canvas id="graficoVolumeAdministrativo"></canvas>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Status do mês</h2>
                        <p>Distribuição atual dos processos do administrativo.</p>
                    </div>
                </div>

                <div class="chart-box chart-box-sm">
                    <canvas id="graficoStatusAdministrativo"></canvas>
                </div>
            </article>
        </section>

        <section class="main-grid relatorios-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Tempo médio por período</h2>
                        <p>Evolução do tempo médio gasto na análise e conclusão dos processos.</p>
                    </div>
                </div>

                <div class="chart-box">
                    <canvas id="graficoTempoAdministrativo"></canvas>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Resumo executivo</h2>
                        <p>Leitura gerencial da performance do administrativo.</p>
                    </div>
                </div>

                <div class="relatorio-resumo-list">
                    <div class="relatorio-resumo-item">
                        <strong>Maior volume</strong>
                        <p>Abril/2026 foi o melhor mês do trimestre em entrada de protocolos e geração de orçamentos.</p>
                    </div>

                    <div class="relatorio-resumo-item">
                        <strong>Eficiência em alta</strong>
                        <p>O setor elevou a quantidade de processos concluídos ao mesmo tempo em que reduziu o tempo médio de análise.</p>
                    </div>

                    <div class="relatorio-resumo-item">
                        <strong>Ponto de atenção</strong>
                        <p>As pendências cresceram junto com a demanda. O gargalo continua sendo documentação incompleta.</p>
                    </div>

                    <div class="relatorio-resumo-item">
                        <strong>Oportunidade</strong>
                        <p>Padronizar checklist de conferência pode aumentar a velocidade de fechamento dos orçamentos.</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="table-card">
            <div class="section-header">
                <div>
                    <h2>Resumo comparativo</h2>
                    <p>Indicadores do administrativo por período.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Recebidos</th>
                            <th>Orçamentos</th>
                            <th>Concluídos</th>
                            <th>Pendências</th>
                            <th>Tempo médio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dadosMensais as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['periodo']) ?></td>
                                <td><?= (int) $item['recebidos'] ?></td>
                                <td><?= (int) $item['orcamentos'] ?></td>
                                <td><?= (int) $item['concluidos'] ?></td>
                                <td><?= (int) $item['pendencias'] ?></td>
                                <td><?= (int) $item['tempo_medio'] ?> min</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labelsMensais = <?= json_encode($labelsGrafico, JSON_UNESCAPED_UNICODE) ?>;
    const dadosRecebidos = <?= json_encode($dadosRecebidos) ?>;
    const dadosOrcamentos = <?= json_encode($dadosOrcamentos) ?>;
    const dadosConcluidos = <?= json_encode($dadosConcluidos) ?>;
    const dadosPendencias = <?= json_encode($dadosPendencias) ?>;
    const dadosTempoMedio = <?= json_encode($dadosTempoMedio) ?>;

    const corPrimary = '#1f4d3a';
    const corSecondary = '#6d8b57';
    const corAccent = '#b28a47';
    const corInfo = '#356a8a';
    const corSuccess = '#1f7a4d';
    const corGrid = 'rgba(34, 49, 40, 0.08)';
    const corTexto = '#223128';

    new Chart(document.getElementById('graficoVolumeAdministrativo'), {
        type: 'bar',
        data: {
            labels: labelsMensais,
            datasets: [
                {
                    label: 'Recebidos',
                    data: dadosRecebidos,
                    backgroundColor: corPrimary,
                    borderRadius: 10
                },
                {
                    label: 'Orçamentos',
                    data: dadosOrcamentos,
                    backgroundColor: corSecondary,
                    borderRadius: 10
                },
                {
                    label: 'Concluídos',
                    data: dadosConcluidos,
                    backgroundColor: corSuccess,
                    borderRadius: 10
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: corTexto,
                        font: { size: 12, weight: '600' }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: corTexto },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: corTexto },
                    grid: { color: corGrid }
                }
            }
        }
    });

    new Chart(document.getElementById('graficoStatusAdministrativo'), {
        type: 'doughnut',
        data: {
            labels: ['Concluídos', 'Pendências', 'Em análise'],
            datasets: [{
                data: [79, 18, 29],
                backgroundColor: [corSuccess, corAccent, corInfo],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: corTexto,
                        font: { size: 12, weight: '600' },
                        padding: 18
                    }
                }
            }
        }
    });

    new Chart(document.getElementById('graficoTempoAdministrativo'), {
        type: 'line',
        data: {
            labels: labelsMensais,
            datasets: [{
                label: 'Tempo médio (min)',
                data: dadosTempoMedio,
                borderColor: corInfo,
                backgroundColor: 'rgba(53, 106, 138, 0.12)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: corInfo,
                pointRadius: 4,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: corTexto,
                        font: { size: 12, weight: '600' }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: corTexto },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: false,
                    ticks: {
                        color: corTexto,
                        callback: function(value) {
                            return value + ' min';
                        }
                    },
                    grid: { color: corGrid }
                }
            }
        }
    });
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>