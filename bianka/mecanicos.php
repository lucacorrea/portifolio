<?php
$pageTitle = 'Mecânicos';
$currentPage = 'mecanicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Mecânicos</h1><p>Equipe técnica e produtividade.</p></div>
    <div class="actions"><button class="btn btn-primary"><i class="icon icon-user-plus"></i> Novo mecânico</button></div>
</div>
<div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="card section-card"><div class="section-title"><h2>Carlos Alberto</h2><span class="badge success">Ativo</span></div><p class="muted">Motor • Suspensão • Freios</p><ul class="list-clean"><li><span>OS em andamento</span><strong>3</strong></li><li><span>Serviços no mês</span><strong>27</strong></li></ul></div>
    <div class="card section-card"><div class="section-title"><h2>Paulo Henrique</h2><span class="badge success">Ativo</span></div><p class="muted">Elétrica • Injeção eletrônica</p><ul class="list-clean"><li><span>OS em andamento</span><strong>2</strong></li><li><span>Serviços no mês</span><strong>21</strong></li></ul></div>
    <div class="card section-card"><div class="section-title"><h2>Renato Costa</h2><span class="badge success">Ativo</span></div><p class="muted">Freios • Revisão geral</p><ul class="list-clean"><li><span>OS em andamento</span><strong>2</strong></li><li><span>Serviços no mês</span><strong>19</strong></li></ul></div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
