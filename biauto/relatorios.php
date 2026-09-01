<?php
$pageTitle = 'Relatórios';
$currentPage = 'relatorios';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Relatórios', 'Indicadores financeiros, operacionais e de produtividade.', [
    ['label' => 'Exportar relatório', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
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
            <thead><tr><th>Mecânico</th><th>OS finalizadas</th><th>Serviços</th><th>Valor movimentado</th><th>Ticket médio</th><th>Ações</th></tr></thead>
            <tbody><tr><td>Carlos Alberto</td><td>31</td><td>47</td><td>R$ 12.680,00</td><td>R$ 409,03</td><td>Ver</td></tr><tr><td>Paulo Henrique</td><td>28</td><td>42</td><td>R$ 10.940,00</td><td>R$ 390,71</td><td>Ver</td></tr><tr><td>Renato Costa</td><td>25</td><td>37</td><td>R$ 9.180,00</td><td>R$ 367,20</td><td>Ver</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards"><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Carlos Alberto</strong><span>31</span></div>
                    <p>47</p>
                    <div class="mobile-card-bottom"><span class="money">R$ 409,03</span><a class="btn" href="#">Ver</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Paulo Henrique</strong><span>28</span></div>
                    <p>42</p>
                    <div class="mobile-card-bottom"><span class="money">R$ 390,71</span><a class="btn" href="#">Ver</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Renato Costa</strong><span>25</span></div>
                    <p>37</p>
                    <div class="mobile-card-bottom"><span class="money">R$ 367,20</span><a class="btn" href="#">Ver</a></div>
                </div></div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
