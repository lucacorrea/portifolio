<?php
$pageTitle = 'Serviços';
$currentPage = 'servicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Serviços</h1><p>Catálogo de serviços e preços-base.</p></div>
    <div class="actions"><button class="btn btn-primary"><i class="icon icon-plus"></i> Novo serviço</button></div>
</div>
<div class="card section-card">
    <div class="filters"><div class="search"><i class="icon icon-search"></i><input class="input" placeholder="Pesquisar serviço"></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Serviço</th><th>Categoria</th><th>Tempo estimado</th><th>Valor base</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr><td>Troca de óleo</td><td>Manutenção</td><td>40 min</td><td class="money">R$ 150,00</td><td><span class="badge success">Ativo</span></td><td><button class="btn">Editar</button></td></tr>
                <tr><td>Revisão de freios</td><td>Freios</td><td>1h 30min</td><td class="money">R$ 250,00</td><td><span class="badge success">Ativo</span></td><td><button class="btn">Editar</button></td></tr>
                <tr><td>Alinhamento</td><td>Suspensão</td><td>45 min</td><td class="money">R$ 120,00</td><td><span class="badge success">Ativo</span></td><td><button class="btn">Editar</button></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
