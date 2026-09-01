<?php
$pageTitle = 'Veículos';
$currentPage = 'veiculos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Veículos', 'Veículos vinculados aos clientes e seu histórico de manutenção.', [
    ['label' => 'Novo veículo', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
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
            <thead><tr><th>Veículo</th><th>Placa</th><th>Cliente</th><th>Ano</th><th>KM atual</th><th>Ações</th></tr></thead>
            <tbody><tr><td>Toyota Hilux SRV</td><td>ABC1D23</td><td>João da Silva</td><td>2022</td><td>83.520 km</td><td>Ver histórico</td></tr><tr><td>Chevrolet Onix LT</td><td>DEF4G56</td><td>Maria Souza</td><td>2020</td><td>52.110 km</td><td>Ver histórico</td></tr><tr><td>Honda Civic EXL</td><td>HIJ7K89</td><td>Lucas Lima</td><td>2019</td><td>68.740 km</td><td>Ver histórico</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards"><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Toyota Hilux SRV</strong><span>ABC1D23</span></div>
                    <p>João da Silva</p>
                    <div class="mobile-card-bottom"><span class="money">83.520 km</span><a class="btn" href="#">Ver histórico</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Chevrolet Onix LT</strong><span>DEF4G56</span></div>
                    <p>Maria Souza</p>
                    <div class="mobile-card-bottom"><span class="money">52.110 km</span><a class="btn" href="#">Ver histórico</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Honda Civic EXL</strong><span>HIJ7K89</span></div>
                    <p>Lucas Lima</p>
                    <div class="mobile-card-bottom"><span class="money">68.740 km</span><a class="btn" href="#">Ver histórico</a></div>
                </div></div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
