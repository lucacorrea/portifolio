<?php
$pageTitle = 'Ordens de Serviço';
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Ordens de Serviço', 'Controle de entrada, execução e finalização das ordens.', [
    ['label' => 'Nova OS', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
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
            <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Mecânico</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody><tr><td>#00128</td><td>João Silva</td><td>Toyota Hilux • ABC1D23</td><td>Carlos</td><td>Em serviço</td><td>Abrir</td></tr><tr><td>#00127</td><td>Maria Souza</td><td>Chevrolet Onix • DEF4G56</td><td>Paulo</td><td>Aguardando peça</td><td>Abrir</td></tr><tr><td>#00126</td><td>Lucas Lima</td><td>Honda Civic • HIJ7K89</td><td>Renato</td><td>Finalizada</td><td>Abrir</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards"><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>#00128</strong><span>João Silva</span></div>
                    <p>Toyota Hilux • ABC1D23</p>
                    <div class="mobile-card-bottom"><span class="money">Em serviço</span><a class="btn" href="#">Abrir</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>#00127</strong><span>Maria Souza</span></div>
                    <p>Chevrolet Onix • DEF4G56</p>
                    <div class="mobile-card-bottom"><span class="money">Aguardando peça</span><a class="btn" href="#">Abrir</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>#00126</strong><span>Lucas Lima</span></div>
                    <p>Honda Civic • HIJ7K89</p>
                    <div class="mobile-card-bottom"><span class="money">Finalizada</span><a class="btn" href="#">Abrir</a></div>
                </div></div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
