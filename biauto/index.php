<?php
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Acompanhe os principais números e a movimentação da oficina.</p>
    </div>
    <div class="actions">
        <a class="btn" href="relatorios.php"><i class="icon icon-sliders-horizontal"></i> Filtrar</a>
        <a class="btn btn-primary" href="ordem_nova.php"><i class="icon icon-plus"></i> Nova OS</a>
    </div>
</div>

<div class="grid stats">
    <div class="card stat-card">
        <div class="stat-label">Ordens abertas</div>
        <div class="stat-value">28</div>
        <div class="stat-note"><span class="metric-positive">↗ 12,3%</span> &nbsp; +3 hoje</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Em execução</div>
        <div class="stat-value">14</div>
        <div class="stat-note"><span class="metric-positive">↗ 8,1%</span> &nbsp; 4 iniciadas hoje</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Aguardando peça</div>
        <div class="stat-value">6</div>
        <div class="stat-note"><span class="metric-negative">↘ atenção</span> &nbsp; 2 acima do prazo</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Finalizadas no mês</div>
        <div class="stat-value">84</div>
        <div class="stat-note"><span class="metric-positive">↗ 17,4%</span> &nbsp; +12 vs. mês anterior</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card section-card chart-card">
        <div class="chart-head">
            <div>
                <div class="section-title" style="margin-bottom:0"><h2>Faturamento</h2></div>
                <div class="chart-value">R$ 32.860,00</div>
                <div class="chart-caption"><span class="metric-positive">↗ 12,23%</span> &nbsp; R$ 4.210,00 acima do mês anterior</div>
            </div>
            <select class="chart-select"><option>Mês</option><option>Semana</option><option>Ano</option></select>
        </div>
        <div class="bar-chart" aria-label="Gráfico de faturamento mensal">
            <div class="bar-group"><div class="bar" style="height:38%"></div><span class="bar-label">Jan</span></div>
            <div class="bar-group"><div class="bar" style="height:49%"></div><span class="bar-label">Fev</span></div>
            <div class="bar-group"><div class="bar" style="height:72%"></div><span class="bar-label">Mar</span></div>
            <div class="bar-group"><div class="bar" style="height:56%"></div><span class="bar-label">Abr</span></div>
            <div class="bar-group"><div class="bar" style="height:58%"></div><span class="bar-label">Mai</span></div>
            <div class="bar-group"><div class="bar" style="height:65%"></div><span class="bar-label">Jun</span></div>
            <div class="bar-group"><div class="bar" style="height:71%"></div><span class="bar-label">Jul</span></div>
            <div class="bar-group"><div class="bar current" style="height:88%"></div><span class="bar-label">Ago</span></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><h2>Operação hoje</h2><span class="subtitle">01 set. 2026</span></div>
        <div class="quick-list">
            <div class="quick-item"><div class="quick-main"><div class="quick-icon"><i class="icon icon-car-front"></i></div><div class="quick-text"><strong>Entradas de veículos</strong><span>Recebidos hoje</span></div></div><div class="quick-value">8</div></div>
            <div class="quick-item"><div class="quick-main"><div class="quick-icon"><i class="icon icon-wrench"></i></div><div class="quick-text"><strong>Serviços iniciados</strong><span>Em execução</span></div></div><div class="quick-value">6</div></div>
            <div class="quick-item"><div class="quick-main"><div class="quick-icon"><i class="icon icon-circle-check-big"></i></div><div class="quick-text"><strong>Entregas previstas</strong><span>Para hoje</span></div></div><div class="quick-value">5</div></div>
            <div class="quick-item"><div class="quick-main"><div class="quick-icon"><i class="icon icon-package-search"></i></div><div class="quick-text"><strong>Estoque baixo</strong><span>Itens para reposição</span></div></div><div class="quick-value">4</div></div>
        </div>
    </div>
</div>

<div class="bottom-grid">
    <div class="card section-card">
        <div class="section-title"><h2>Atividade recente</h2><select class="chart-select"><option>Últimas 24h</option><option>7 dias</option></select></div>
        <div class="activity-list">
            <div class="activity-row header"><div>Cliente</div><div>Status</div><div>Veículo</div><div>Horário</div><div>Valor</div></div>
            <div class="activity-row">
                <div class="activity-customer"><div class="mini-avatar">JS</div><div><strong>João Silva</strong><span>OS #00128</span></div></div>
                <div><span class="badge info">Em serviço</span></div><div>Toyota Hilux</div><div>5 min</div><div class="money">R$ 1.850</div>
            </div>
            <div class="activity-row">
                <div class="activity-customer"><div class="mini-avatar">MS</div><div><strong>Maria Souza</strong><span>OS #00127</span></div></div>
                <div><span class="badge warning">Aguardando peça</span></div><div>Chevrolet Onix</div><div>12 min</div><div class="money">R$ 780</div>
            </div>
            <div class="activity-row">
                <div class="activity-customer"><div class="mini-avatar">LL</div><div><strong>Lucas Lima</strong><span>OS #00126</span></div></div>
                <div><span class="badge success">Finalizada</span></div><div>Honda Civic</div><div>18 min</div><div class="money">R$ 1.240</div>
            </div>
            <div class="activity-row">
                <div class="activity-customer"><div class="mini-avatar">RA</div><div><strong>Rita Alves</strong><span>Orçamento #0097</span></div></div>
                <div><span class="badge success">Aprovado</span></div><div>Fiat Argo</div><div>31 min</div><div class="money">R$ 940</div>
            </div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><h2>Distribuição das OS</h2><select class="chart-select"><option>Mês atual</option></select></div>
        <div class="donut-wrap">
            <div class="donut">
                <div class="donut-center"><div><strong>84</strong><span>ordens</span></div></div>
            </div>
        </div>
        <div class="donut-legend">
            <span><i class="legend-dot"></i>Finalizadas 40%</span>
            <span><i class="legend-dot gray"></i>Em serviço 30%</span>
            <span><i class="legend-dot light"></i>Outras 30%</span>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
