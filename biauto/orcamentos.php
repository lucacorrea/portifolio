<?php
$pageTitle = 'Orçamentos';
$currentPage = 'orcamentos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Orçamentos</h1><p>Crie, acompanhe e converta orçamentos em OS.</p></div>
    <div class="actions"><button class="btn btn-primary"><i class="icon icon-file-plus-2"></i> Novo orçamento</button></div>
</div>
<div class="card section-card">
    <div class="filters">
        <div class="search"><i class="icon icon-search"></i><input class="input" placeholder="Número, cliente, placa ou veículo"></div>
        <select class="select" style="max-width:220px"><option>Todos os status</option><option>Rascunho</option><option>Aguardando aprovação</option><option>Aprovado</option><option>Recusado</option><option>Convertido em OS</option></select>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Nº</th><th>Cliente</th><th>Veículo</th><th>Emissão</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
        <tr><td>#ORC-0098</td><td>Antônio Melo</td><td>VW Saveiro</td><td>01/09/2026</td><td><span class="badge warning">Aguardando aprovação</span></td><td class="money">R$ 2.180,00</td><td><button class="btn">Abrir</button></td></tr>
        <tr><td>#ORC-0097</td><td>Rita Alves</td><td>Fiat Argo</td><td>31/08/2026</td><td><span class="badge success">Aprovado</span></td><td class="money">R$ 940,00</td><td><button class="btn btn-primary">Converter em OS</button></td></tr>
        <tr><td>#ORC-0096</td><td>José Pereira</td><td>Ford Ranger</td><td>30/08/2026</td><td><span class="badge danger">Recusado</span></td><td class="money">R$ 3.600,00</td><td><button class="btn">Abrir</button></td></tr>
    </tbody></table></div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
