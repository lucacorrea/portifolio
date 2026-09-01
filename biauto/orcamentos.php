<?php
$pageTitle = 'Orçamentos';
$currentPage = 'orcamentos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Orçamentos', 'Crie, acompanhe e converta orçamentos em ordens de serviço.', [
    ['label' => 'Novo orçamento', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
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
            <thead><tr><th>Nº</th><th>Cliente</th><th>Veículo</th><th>Emissão</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody><tr><td>#ORC-0098</td><td>Antônio Melo</td><td>VW Saveiro</td><td>01/09/2026</td><td>Aguardando aprovação</td><td>Abrir</td></tr><tr><td>#ORC-0097</td><td>Rita Alves</td><td>Fiat Argo</td><td>31/08/2026</td><td>Aprovado</td><td>Converter</td></tr><tr><td>#ORC-0096</td><td>José Pereira</td><td>Ford Ranger</td><td>30/08/2026</td><td>Recusado</td><td>Abrir</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards"><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>#ORC-0098</strong><span>Antônio Melo</span></div>
                    <p>VW Saveiro</p>
                    <div class="mobile-card-bottom"><span class="money">Aguardando aprovação</span><a class="btn" href="#">Abrir</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>#ORC-0097</strong><span>Rita Alves</span></div>
                    <p>Fiat Argo</p>
                    <div class="mobile-card-bottom"><span class="money">Aprovado</span><a class="btn" href="#">Converter</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>#ORC-0096</strong><span>José Pereira</span></div>
                    <p>Ford Ranger</p>
                    <div class="mobile-card-bottom"><span class="money">Recusado</span><a class="btn" href="#">Abrir</a></div>
                </div></div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
