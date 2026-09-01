<?php
$pageTitle = 'Veículos';
$currentPage = 'veiculos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Veículos</h1><p>Veículos vinculados aos clientes.</p></div>
    <div class="actions"><button class="btn btn-primary"><i class="icon icon-plus"></i> Novo veículo</button></div>
</div>
<div class="card section-card">
    <div class="filters">
        <div class="search"><i class="icon icon-search"></i><input class="input" placeholder="Placa, modelo, marca ou proprietário"></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Veículo</th><th>Placa</th><th>Cliente</th><th>Ano</th><th>KM atual</th><th>Último serviço</th><th></th></tr></thead>
            <tbody>
                <tr><td>Toyota Hilux SRV</td><td>ABC1D23</td><td>João da Silva</td><td>2022</td><td>83.520 km</td><td>29/08/2026</td><td><button class="btn">Ver histórico</button></td></tr>
                <tr><td>Chevrolet Onix LT</td><td>DEF4G56</td><td>Maria Souza</td><td>2020</td><td>52.110 km</td><td>01/09/2026</td><td><button class="btn">Ver histórico</button></td></tr>
                <tr><td>Honda Civic EXL</td><td>HIJ7K89</td><td>Lucas Lima</td><td>2019</td><td>68.740 km</td><td>31/08/2026</td><td><button class="btn">Ver histórico</button></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
