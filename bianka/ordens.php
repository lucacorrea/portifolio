<?php
$pageTitle = 'Ordens de Serviço';
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Ordens de Serviço</h1><p>Controle de entrada, execução e finalização.</p></div>
    <div class="actions"><a class="btn btn-primary" href="ordem_nova.php"><i class="icon icon-plus"></i> Nova OS</a></div>
</div>
<div class="card section-card">
    <div class="filters">
        <div class="search"><i class="icon icon-search"></i><input class="input" placeholder="OS, cliente, placa ou veículo"></div>
        <select class="select" style="max-width:190px"><option>Todos os status</option><option>Aberta</option><option>Em serviço</option><option>Aguardando peça</option><option>Finalizada</option></select>
        <select class="select" style="max-width:180px"><option>Todos os mecânicos</option><option>Carlos</option><option>Paulo</option><option>Renato</option></select>
    </div>
    <div class="table-wrap table-desktop">
        <table>
            <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Mecânico</th><th>Entrada</th><th>Status</th><th>Total</th><th></th></tr></thead>
            <tbody>
                <tr><td>#00128</td><td>João Silva</td><td>Toyota Hilux • ABC1D23</td><td>Carlos</td><td>01/09/2026</td><td><span class="badge info">Em serviço</span></td><td class="money">R$ 1.850,00</td><td><a class="btn" href="ordem_detalhe.php">Abrir</a></td></tr>
                <tr><td>#00127</td><td>Maria Souza</td><td>Chevrolet Onix • DEF4G56</td><td>Paulo</td><td>01/09/2026</td><td><span class="badge warning">Aguardando peça</span></td><td class="money">R$ 780,00</td><td><a class="btn" href="ordem_detalhe.php">Abrir</a></td></tr>
                <tr><td>#00126</td><td>Lucas Lima</td><td>Honda Civic • HIJ7K89</td><td>Renato</td><td>31/08/2026</td><td><span class="badge success">Finalizada</span></td><td class="money">R$ 1.240,00</td><td><a class="btn" href="ordem_detalhe.php">Abrir</a></td></tr>
            </tbody>
        </table>
    </div>
    <div class="cards-mobile">
        <div class="card mobile-record"><div class="mobile-record-head"><strong>#00128 • Hilux</strong><span class="badge info">Em serviço</span></div><p>João Silva • ABC1D23</p><p>Mecânico: Carlos</p><div class="mobile-record-footer"><span class="money">R$ 1.850,00</span><a class="btn" href="ordem_detalhe.php">Abrir</a></div></div>
        <div class="card mobile-record"><div class="mobile-record-head"><strong>#00127 • Onix</strong><span class="badge warning">Aguardando peça</span></div><p>Maria Souza • DEF4G56</p><p>Mecânico: Paulo</p><div class="mobile-record-footer"><span class="money">R$ 780,00</span><a class="btn" href="ordem_detalhe.php">Abrir</a></div></div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
