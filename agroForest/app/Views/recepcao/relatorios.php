<?php
$paginaAtual = 'relatorios';
$paginaTitulo = 'Relatórios';
$paginaDescricao = 'Indicadores operacionais da recepção e volume de atendimento.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Exportar Relatório';
$linkBotaoAcao = '#';
$tituloPagina = 'Recepção - Relatórios';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';

$dadosMensais = [
    ['periodo' => 'Fevereiro/2026', 'atendimentos' => 102, 'encaminhados' => 70, 'pendencias' => 11, 'tempo_medio' => 13],
    ['periodo' => 'Março/2026', 'atendimentos' => 114, 'encaminhados' => 77, 'pendencias' => 14, 'tempo_medio' => 12],
    ['periodo' => 'Abril/2026', 'atendimentos' => 128, 'encaminhados' => 89, 'pendencias' => 17, 'tempo_medio' => 11],
];

$labelsGrafico = array_column($dadosMensais, 'periodo');
$atendimentosGrafico = array_column($dadosMensais, 'atendimentos');
$encaminhadosGrafico = array_column($dadosMensais, 'encaminhados');
$pendenciasGrafico = array_column($dadosMensais, 'pendencias');
$tempoMedioGrafico = array_column($dadosMensais, 'tempo_medio');

require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">📞</div>
                    <span class="trend up">+12%</span>
                </div>
                <h3>128</h3>
                <p>Atendimentos no mês</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">📤</div>
                    <span class="trend up">+9%</span>
                </div>
                <h3>89</h3>
                <p>Protocolos concluídos</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">⏳</div>
                    <span class="trend warn">+3</span>
                </div>
                <h3>17</h3>
                <p>Pendências registradas</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-info">⏱️</div>
                    <span class="trend up">-1 min</span>
                </div>
                <h3>11 min</h3>
                <p>Tempo médio por atendimento</p>
            </article>
        </section>

        <section class="main-grid relatorios-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Atendimentos por período</h2>
                        <p>Comparativo entre atendimentos, encaminhamentos e pendências.</p>
                    </div>
                </div>

                <div class="chart-box">
                    <canvas id="graficoAtendimentos"></canvas>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Status operacional</h2>
                        <p>Distribuição atual dos protocolos do mês.</p>
                    </div>
                </div>

                <div class="chart-box chart-box-sm">
                    <canvas id="graficoStatus"></canvas>
                </div>
            </article>
        </section>

        <section class="main-grid relatorios-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Tempo médio por período</h2>
                        <p>Evolução do tempo médio gasto no atendimento.</p>
                    </div>
                </div>

                <div class="chart-box">
                    <canvas id="graficoTempo"></canvas>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Resumo rápido</h2>
                        <p>Leitura executiva do desempenho da recepção.</p>
                    </div>
                </div>

                <div class="relatorio-resumo-list">
                    <div class="relatorio-resumo-item">
                        <strong>Melhor mês em volume</strong>
                        <p>Abril/2026 registrou o maior número de atendimentos do trimestre.</p>
                    </div>

                    <div class="relatorio-resumo-item">
                        <strong>Maior taxa de encaminhamento</strong>
                        <p>O setor encerrou abril com 89 protocolos concluídos e enviados.</p>
                    </div>

                    <div class="relatorio-resumo-item">
                        <strong>Tempo de atendimento melhorando</strong>
                        <p>O tempo médio caiu de 13 para 11 minutos no período analisado.</p>
                    </div>

                    <div class="relatorio-resumo-item">
                        <strong>Ponto de atenção</strong>
                        <p>As pendências cresceram junto com o volume. Vale reforçar a conferência documental.</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="table-card">
            <div class="section-header">
                <div>
                    <h2>Resumo mensal</h2>
                    <p>Fechamento comparativo por período.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Atendimentos</th>
                            <th>Encaminhados</th>
                            <th>Pendências</th>
                            <th>Tempo médio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dadosMensais as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['periodo']) ?></td>
                                <td><?= (int) $item['atendimentos'] ?></td>
                                <td><?= (int) $item['encaminhados'] ?></td>
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
    const dadosAtendimentos = <?= json_encode($atendimentosGrafico) ?>;
    const dadosEncaminhados = <?= json_encode($encaminhadosGrafico) ?>;
    const dadosPendencias = <?= json_encode($pendenciasGrafico) ?>;
    const dadosTempoMedio = <?= json_encode($tempoMedioGrafico) ?>;

    const corPrimary = '#1f4d3a';
    const corSecondary = '#6d8b57';
    const corAccent = '#b28a47';
    const corInfo = '#356a8a';
    const corGrid = 'rgba(34, 49, 40, 0.08)';
    const corTexto = '#223128';

    new Chart(document.getElementById('graficoAtendimentos'), {
        type: 'bar',
        data: {
            labels: labelsMensais,
            datasets: [
                {
                    label: 'Atendimentos',
                    data: dadosAtendimentos,
                    backgroundColor: corPrimary,
                    borderRadius: 10
                },
                {
                    label: 'Encaminhados',
                    data: dadosEncaminhados,
                    backgroundColor: corSecondary,
                    borderRadius: 10
                },
                {
                    label: 'Pendências',
                    data: dadosPendencias,
                    backgroundColor: corAccent,
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

    new Chart(document.getElementById('graficoStatus'), {
        type: 'doughnut',
        data: {
            labels: ['Concluídos', 'Pendentes', 'Em andamento'],
            datasets: [{
                data: [89, 17, 22],
                backgroundColor: [corPrimary, corAccent, corInfo],
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

    new Chart(document.getElementById('graficoTempo'), {
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