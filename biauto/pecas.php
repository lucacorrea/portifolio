<?php
$pageTitle = 'Peças';
$currentPage = 'pecas';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Peças', 'Controle de estoque, custo e valor de venda.', [
    ['label' => 'Nova peça', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card">
    <div class="filters">
        <div class="filter-grow">
            <input class="input" placeholder="Pesquisar...">
        </div>
        <select class="select"><option>Todos os status</option></select>
    </div>
    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Produto</th><th>Código</th><th>Estoque</th><th>Mínimo</th><th>Venda</th><th>Ações</th></tr></thead>
            <tbody><tr><td>Filtro de óleo</td><td>FLT-001</td><td>3</td><td>5</td><td>R$ 50,00</td><td>Editar</td></tr><tr><td>Óleo 5W30</td><td>OLE-530</td><td>20</td><td>10</td><td>R$ 65,00</td><td>Editar</td></tr><tr><td>Pastilha dianteira</td><td>PST-014</td><td>2</td><td>4</td><td>R$ 260,00</td><td>Editar</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards"><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Filtro de óleo</strong><span>FLT-001</span></div>
                    <p>3</p>
                    <div class="mobile-card-bottom"><span class="money">R$ 50,00</span><a class="btn" href="#">Editar</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Óleo 5W30</strong><span>OLE-530</span></div>
                    <p>20</p>
                    <div class="mobile-card-bottom"><span class="money">R$ 65,00</span><a class="btn" href="#">Editar</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Pastilha dianteira</strong><span>PST-014</span></div>
                    <p>2</p>
                    <div class="mobile-card-bottom"><span class="money">R$ 260,00</span><a class="btn" href="#">Editar</a></div>
                </div></div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
