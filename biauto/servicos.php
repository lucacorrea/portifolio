<?php
$pageTitle = 'Serviços';
$currentPage = 'servicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Serviços', 'Catálogo de serviços com categoria, tempo estimado e preço-base.', [
    ['label' => 'Novo serviço', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
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
            <thead><tr><th>Serviço</th><th>Categoria</th><th>Tempo estimado</th><th>Valor base</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody><tr><td>Troca de óleo</td><td>Manutenção</td><td>40 min</td><td>R$ 150,00</td><td>Ativo</td><td>Editar</td></tr><tr><td>Revisão de freios</td><td>Freios</td><td>1h 30min</td><td>R$ 250,00</td><td>Ativo</td><td>Editar</td></tr><tr><td>Alinhamento</td><td>Suspensão</td><td>45 min</td><td>R$ 120,00</td><td>Ativo</td><td>Editar</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards"><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Troca de óleo</strong><span>Manutenção</span></div>
                    <p>40 min</p>
                    <div class="mobile-card-bottom"><span class="money">Ativo</span><a class="btn" href="#">Editar</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Revisão de freios</strong><span>Freios</span></div>
                    <p>1h 30min</p>
                    <div class="mobile-card-bottom"><span class="money">Ativo</span><a class="btn" href="#">Editar</a></div>
                </div><div class="card mobile-card">
                    <div class="mobile-card-top"><strong>Alinhamento</strong><span>Suspensão</span></div>
                    <p>45 min</p>
                    <div class="mobile-card-bottom"><span class="money">Ativo</span><a class="btn" href="#">Editar</a></div>
                </div></div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
