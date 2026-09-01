<?php
$pageTitle = 'Relatórios';
$currentPage = 'relatorios';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Relatórios</h1><p>Indicadores financeiros, operacionais e de produtividade.</p></div>
    <div class="actions"><button class="btn"><i class="icon icon-file-down"></i> Exportar PDF</button><button class="btn"><i class="icon icon-printer"></i> Imprimir</button></div>
</div>
<div class="card section-card">
    <div class="filters">
        <div style="min-width:180px"><label class="muted" style="font-size:11px">Data inicial</label><input class="input" type="date" value="2026-09-01"></div>
        <div style="min-width:180px"><label class="muted" style="font-size:11px">Data final</label><input class="input" type="date" value="2026-09-30"></div>
        <div style="min-width:220px"><label class="muted" style="font-size:11px">Relatório</label><select class="select"><option>Financeiro</option><option>Ordens de serviço</option><option>Serviços realizados</option><option>Produtividade por mecânico</option><option>Peças</option></select></div>
        <div style="align-self:end"><button class="btn btn-primary">Aplicar filtros</button></div>
    </div>
</div>
<div class="grid stats">
    <div class="card stat-card"><div class="stat-label">Faturamento</div><div class="stat-value">R$ 32,8 mil</div></div>
    <div class="card stat-card"><div class="stat-label">OS finalizadas</div><div class="stat-value">84</div></div>
    <div class="card stat-card"><div class="stat-label">Ticket médio</div><div class="stat-value">R$ 390</div></div>
    <div class="card stat-card"><div class="stat-label">Peças vendidas</div><div class="stat-value">173</div></div>
    <div class="card stat-card"><div class="stat-label">Serviços</div><div class="stat-value">126</div></div>
    <div class="card stat-card"><div class="stat-label">Clientes atendidos</div><div class="stat-value">71</div></div>
</div>
<div class="card section-card">
    <div class="section-title"><h2>Resumo por mecânico</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Mecânico</th><th>OS finalizadas</th><th>Serviços</th><th>Valor movimentado</th><th>Ticket médio</th></tr></thead><tbody>
        <tr><td>Carlos Alberto</td><td>31</td><td>47</td><td class="money">R$ 12.680,00</td><td>R$ 409,03</td></tr>
        <tr><td>Paulo Henrique</td><td>28</td><td>42</td><td class="money">R$ 10.940,00</td><td>R$ 390,71</td></tr>
        <tr><td>Renato Costa</td><td>25</td><td>37</td><td class="money">R$ 9.180,00</td><td>R$ 367,20</td></tr>
    </tbody></table></div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
