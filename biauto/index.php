<?php
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Visão geral da operação da oficina.</p>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="ordem_nova.php"><i class="icon icon-plus"></i> Nova OS</a>
        <a class="btn" href="orcamentos.php"><i class="icon icon-file-plus-2"></i> Novo orçamento</a>
    </div>
</div>

<div class="grid stats">
    <div class="card stat-card"><div class="stat-top"><div><div class="stat-label">OS abertas</div><div class="stat-value">12</div></div><div class="stat-icon"><i class="icon icon-clipboard-list"></i></div></div><div class="stat-note">4 novas hoje</div></div>
    <div class="card stat-card"><div class="stat-top"><div><div class="stat-label">Em execução</div><div class="stat-value">7</div></div><div class="stat-icon"><i class="icon icon-wrench"></i></div></div><div class="stat-note">58% das abertas</div></div>
    <div class="card stat-card"><div class="stat-top"><div><div class="stat-label">Aguardando peça</div><div class="stat-value">3</div></div><div class="stat-icon"><i class="icon icon-package-clock"></i></div></div><div class="stat-note">Requer atenção</div></div>
    <div class="card stat-card"><div class="stat-top"><div><div class="stat-label">Finalizadas hoje</div><div class="stat-value">5</div></div><div class="stat-icon"><i class="icon icon-circle-check-big"></i></div></div><div class="stat-note">+2 vs. ontem</div></div>
    <div class="card stat-card"><div class="stat-top"><div><div class="stat-label">Orçamentos pendentes</div><div class="stat-value">6</div></div><div class="stat-icon"><i class="icon icon-file-clock"></i></div></div><div class="stat-note">R$ 8.450,00 em análise</div></div>
    <div class="card stat-card"><div class="stat-top"><div><div class="stat-label">Faturamento do mês</div><div class="stat-value">R$ 32,8 mil</div></div><div class="stat-icon"><i class="icon icon-chart-no-axes-combined"></i></div></div><div class="stat-note">Meta mensal: R$ 45 mil</div></div>
</div>

<div class="card section-card">
    <div class="section-title">
        <h2>Ordens de Serviço recentes</h2>
        <a class="btn" href="ordens.php">Ver todas</a>
    </div>
    <div class="table-wrap table-desktop">
        <table>
            <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Mecânico</th><th>Entrada</th><th>Status</th><th>Valor</th><th></th></tr></thead>
            <tbody>
                <tr><td>#00128</td><td>João Silva</td><td>Toyota Hilux</td><td>Carlos</td><td>01/09/2026</td><td><span class="badge info"><span class="dot"></span> Em serviço</span></td><td class="money">R$ 1.850,00</td><td><a class="btn" href="ordem_detalhe.php">Ver</a></td></tr>
                <tr><td>#00127</td><td>Maria Souza</td><td>Chevrolet Onix</td><td>Paulo</td><td>01/09/2026</td><td><span class="badge warning"><span class="dot"></span> Aguardando peça</span></td><td class="money">R$ 780,00</td><td><a class="btn" href="ordem_detalhe.php">Ver</a></td></tr>
                <tr><td>#00126</td><td>Lucas Lima</td><td>Honda Civic</td><td>Renato</td><td>31/08/2026</td><td><span class="badge success"><span class="dot"></span> Finalizada</span></td><td class="money">R$ 1.240,00</td><td><a class="btn" href="ordem_detalhe.php">Ver</a></td></tr>
            </tbody>
        </table>
    </div>
    <div class="cards-mobile">
        <div class="card mobile-record">
            <div class="mobile-record-head"><strong>#00128 • Toyota Hilux</strong><span class="badge info">Em serviço</span></div>
            <p>João Silva • Mecânico: Carlos</p>
            <div class="mobile-record-footer"><span class="money">R$ 1.850,00</span><a class="btn" href="ordem_detalhe.php">Ver</a></div>
        </div>
    </div>
</div>

<div class="kpi-row">
    <div class="card section-card">
        <div class="section-title"><h2>Movimento da oficina</h2></div>
        <ul class="list-clean">
            <li><span>Entradas hoje</span><strong>8 veículos</strong></li>
            <li><span>Entregas previstas para hoje</span><strong>6 veículos</strong></li>
            <li><span>Orçamentos aguardando retorno</span><strong>6</strong></li>
            <li><span>Peças com estoque baixo</span><strong>4 itens</strong></li>
        </ul>
    </div>
    <div class="card section-card">
        <div class="section-title"><h2>Atenção</h2></div>
        <ul class="list-clean">
            <li><span class="muted">OS #00127</span><strong>Aguardando peça</strong></li>
            <li><span class="muted">Filtro de óleo</span><strong>3 un.</strong></li>
            <li><span class="muted">Pastilha dianteira</span><strong>2 un.</strong></li>
        </ul>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
