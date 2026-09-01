<?php
$pageTitle = 'Clientes';
$currentPage = 'clientes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Clientes', 'Cadastro e histórico dos clientes atendidos pela oficina.', [
    ['label' => 'Novo cliente', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
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
            <thead><tr><th>Cliente</th><th>CPF/CNPJ</th><th>Telefone</th><th>Veículos</th><th>Último serviço</th><th>Ações</th></tr></thead>
            <tbody><tr><td>João da Silva</td><td>***.***.***-12</td><td>(92) 99999-1111</td><td>2</td><td>29/08/2026</td><td>Ver ficha</td></tr><tr><td>Maria Souza</td><td>***.***.***-44</td><td>(92) 98888-2222</td><td>1</td><td>01/09/2026</td><td>Ver ficha</td></tr><tr><td>Lucas Lima</td><td>***.***.***-78</td><td>(92) 97777-3333</td><td>1</td><td>31/08/2026</td><td>Ver ficha</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards"><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>João da Silva</strong><span>***.***.***-12</span></div>
                    <p>(92) 99999-1111</p>
                    <div class="mobile-card-bottom"><span class="money">29/08/2026</span><a class="btn" href="#">Ver ficha</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Maria Souza</strong><span>***.***.***-44</span></div>
                    <p>(92) 98888-2222</p>
                    <div class="mobile-card-bottom"><span class="money">01/09/2026</span><a class="btn" href="#">Ver ficha</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Lucas Lima</strong><span>***.***.***-78</span></div>
                    <p>(92) 97777-3333</p>
                    <div class="mobile-card-bottom"><span class="money">31/08/2026</span><a class="btn" href="#">Ver ficha</a></div>
                </div></div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
