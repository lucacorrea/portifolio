<?php
$pageTitle = 'Peças';
$currentPage = 'pecas';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Peças</h1><p>Controle simplificado de estoque e preço.</p></div>
    <div class="actions"><button class="btn btn-primary"><i class="icon icon-package-plus"></i> Nova peça</button></div>
</div>
<div class="card section-card">
    <div class="filters"><div class="search"><i class="icon icon-search"></i><input class="input" placeholder="Produto, código ou fornecedor"></div><select class="select" style="max-width:200px"><option>Todos os estoques</option><option>Estoque baixo</option><option>Sem estoque</option></select></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Produto</th><th>Código</th><th>Estoque</th><th>Mínimo</th><th>Custo</th><th>Venda</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr><td>Filtro de óleo</td><td>FLT-001</td><td>3</td><td>5</td><td>R$ 35,00</td><td class="money">R$ 50,00</td><td><span class="badge danger">Estoque baixo</span></td><td><button class="btn">Editar</button></td></tr>
                <tr><td>Óleo 5W30</td><td>OLE-530</td><td>20</td><td>10</td><td>R$ 45,00</td><td class="money">R$ 65,00</td><td><span class="badge success">Normal</span></td><td><button class="btn">Editar</button></td></tr>
                <tr><td>Pastilha dianteira</td><td>PST-014</td><td>2</td><td>4</td><td>R$ 180,00</td><td class="money">R$ 260,00</td><td><span class="badge danger">Estoque baixo</span></td><td><button class="btn">Editar</button></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
