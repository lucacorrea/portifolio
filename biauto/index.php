<?php
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Dashboard', 'Acompanhe os principais números, o faturamento e a movimentação da oficina.', [
    ['label' => 'Filtrar', 'href' => 'relatorios.php', 'icon' => 'filter', 'class' => 'btn-secondary'],
    ['label' => 'Nova OS', 'href' => 'ordem_nova.php', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="grid stats">
    <div class="card stat-card">
        <div class="stat-top">
            <div>
                <div class="stat-label">Ordens abertas</div>
                <div class="stat-value">28</div>
                <div class="stat-note"><span class="kpi-positive">+12,3%</span> em relação à semana passada</div>
            </div>
            <div class="stat-chip"><?= ui_icon('os') ?></div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-top">
            <div>
                <div class="stat-label">Em execução</div>
                <div class="stat-value">14</div>
                <div class="stat-note"><span class="kpi-positive">+4</span> iniciadas hoje</div>
            </div>
            <div class="stat-chip"><?= ui_icon('mechanic') ?></div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-top">
            <div>
                <div class="stat-label">Aguardando peça</div>
                <div class="stat-value">6</div>
                <div class="stat-note"><span class="kpi-negative">2</span> acima do prazo previsto</div>
            </div>
            <div class="stat-chip"><?= ui_icon('parts') ?></div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-top">
            <div>
                <div class="stat-label">Finalizadas no mês</div>
                <div class="stat-value">84</div>
                <div class="stat-note"><span class="kpi-positive">+17,4%</span> em comparação ao mês anterior</div>
            </div>
            <div class="stat-chip"><?= ui_icon('report') ?></div>
        </div>
    </div>
</div>

<div class="dashboard-layout">
    <div class="card section-card">
        <div class="chart-meta">
            <div>
                <h2 style="margin:0;font-size:20px;letter-spacing:-.03em">Faturamento</h2>
                <div class="chart-value">R$ 32.860,00</div>
                <div class="chart-subtitle"><span class="kpi-positive">+12,23%</span> • R$ 4.210,00 acima do mês anterior</div>
            </div>
            <select class="tiny-select">
                <option>Mês</option>
                <option>Semana</option>
                <option>Ano</option>
            </select>
        </div>

        <div class="bar-chart">
            <div class="bar-wrap"><div class="bar" style="height:38%"></div><span class="bar-label">Jan</span></div>
            <div class="bar-wrap"><div class="bar" style="height:48%"></div><span class="bar-label">Fev</span></div>
            <div class="bar-wrap"><div class="bar" style="height:71%"></div><span class="bar-label">Mar</span></div>
            <div class="bar-wrap"><div class="bar" style="height:54%"></div><span class="bar-label">Abr</span></div>
            <div class="bar-wrap"><div class="bar" style="height:58%"></div><span class="bar-label">Mai</span></div>
            <div class="bar-wrap"><div class="bar" style="height:66%"></div><span class="bar-label">Jun</span></div>
            <div class="bar-wrap"><div class="bar" style="height:74%"></div><span class="bar-label">Jul</span></div>
            <div class="bar-wrap"><div class="bar current" style="height:90%"></div><span class="bar-label">Ago</span></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title">
            <div>
                <h2>Operação de hoje</h2>
                <p>Resumo rápido do turno atual</p>
            </div>
            <span class="muted">01 set. 2026</span>
        </div>

        <div class="operation-list">
            <div class="operation-item">
                <div class="operation-main">
                    <div class="operation-icon"><?= ui_icon('vehicle') ?></div>
                    <div class="operation-copy"><strong>Entradas de veículos</strong><span>Recebidos hoje</span></div>
                </div>
                <div class="operation-value">8</div>
            </div>
            <div class="operation-item">
                <div class="operation-main">
                    <div class="operation-icon"><?= ui_icon('mechanic') ?></div>
                    <div class="operation-copy"><strong>Serviços iniciados</strong><span>Ordens em execução</span></div>
                </div>
                <div class="operation-value">6</div>
            </div>
            <div class="operation-item">
                <div class="operation-main">
                    <div class="operation-icon"><?= ui_icon('os') ?></div>
                    <div class="operation-copy"><strong>Entregas previstas</strong><span>Programadas para hoje</span></div>
                </div>
                <div class="operation-value">5</div>
            </div>
            <div class="operation-item">
                <div class="operation-main">
                    <div class="operation-icon"><?= ui_icon('parts') ?></div>
                    <div class="operation-copy"><strong>Estoque baixo</strong><span>Itens para reposição</span></div>
                </div>
                <div class="operation-value">4</div>
            </div>
        </div>
    </div>
</div>

<div class="bottom-layout">
    <div class="card section-card">
        <div class="section-title">
            <div>
                <h2>Atividade recente</h2>
                <p>Movimentação das últimas 24 horas</p>
            </div>
            <select class="tiny-select">
                <option>Últimas 24h</option>
                <option>7 dias</option>
            </select>
        </div>

        <div class="activity-table">
            <div class="activity-row header">
                <div>Cliente</div>
                <div>Status</div>
                <div>Veículo</div>
                <div>Horário</div>
                <div>Valor</div>
                <div></div>
            </div>

            <div class="activity-row">
                <div class="activity-user">
                    <div class="activity-avatar">JS</div>
                    <div><strong>João Silva</strong><span>OS #00128</span></div>
                </div>
                <div><span class="badge info"><span class="dot"></span>Em serviço</span></div>
                <div>Toyota Hilux</div>
                <div>08:42</div>
                <div class="money">R$ 1.850</div>
                <div><a class="btn" href="ordem_detalhe.php">Abrir</a></div>
            </div>

            <div class="activity-row">
                <div class="activity-user">
                    <div class="activity-avatar">MS</div>
                    <div><strong>Maria Souza</strong><span>OS #00127</span></div>
                </div>
                <div><span class="badge warning"><span class="dot"></span>Aguardando peça</span></div>
                <div>Chevrolet Onix</div>
                <div>09:15</div>
                <div class="money">R$ 780</div>
                <div><a class="btn" href="ordem_detalhe.php">Abrir</a></div>
            </div>

            <div class="activity-row">
                <div class="activity-user">
                    <div class="activity-avatar">LL</div>
                    <div><strong>Lucas Lima</strong><span>OS #00126</span></div>
                </div>
                <div><span class="badge success"><span class="dot"></span>Finalizada</span></div>
                <div>Honda Civic</div>
                <div>10:08</div>
                <div class="money">R$ 1.240</div>
                <div><a class="btn" href="ordem_detalhe.php">Abrir</a></div>
            </div>

            <div class="activity-row">
                <div class="activity-user">
                    <div class="activity-avatar">RA</div>
                    <div><strong>Rita Alves</strong><span>Orçamento #0097</span></div>
                </div>
                <div><span class="badge success"><span class="dot"></span>Aprovado</span></div>
                <div>Fiat Argo</div>
                <div>10:41</div>
                <div class="money">R$ 940</div>
                <div><a class="btn" href="orcamentos.php">Ver</a></div>
            </div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title">
            <div>
                <h2>Distribuição das OS</h2>
                <p>Situação do mês atual</p>
            </div>
        </div>

        <div class="donut-area">
            <div class="donut">
                <div class="donut-center">
                    <div>
                        <strong>84</strong>
                        <span>ordens no mês</span>
                    </div>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-left"><span class="legend-color primary"></span> Finalizadas</div>
                    <strong>44%</strong>
                </div>
                <div class="legend-item">
                    <div class="legend-left"><span class="legend-color gray"></span> Em serviço</div>
                    <strong>30%</strong>
                </div>
                <div class="legend-item">
                    <div class="legend-left"><span class="legend-color light"></span> Outras</div>
                    <strong>26%</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
