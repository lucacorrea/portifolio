<?php
$pageTitle = 'Mecânicos';
$currentPage = 'mecanicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Mecânicos', 'Equipe técnica, especialidades e produtividade.', [
    ['label' => 'Novo mecânico', 'href' => '#', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="grid stats" style="grid-template-columns:repeat(3,minmax(0,1fr));">
    <div class="card section-card">
        <div class="section-title"><h2>Carlos Alberto</h2><span class="badge success"><span class="dot"></span>Ativo</span></div>
        <p class="muted">Motor • Suspensão • Freios</p>
        <div class="operation-list">
            <div class="operation-item"><div class="operation-copy"><strong>OS em andamento</strong><span>No momento</span></div><div class="operation-value">3</div></div>
            <div class="operation-item"><div class="operation-copy"><strong>Serviços no mês</strong><span>Produtividade</span></div><div class="operation-value">27</div></div>
        </div>
    </div>
    <div class="card section-card">
        <div class="section-title"><h2>Paulo Henrique</h2><span class="badge success"><span class="dot"></span>Ativo</span></div>
        <p class="muted">Elétrica • Injeção eletrônica</p>
        <div class="operation-list">
            <div class="operation-item"><div class="operation-copy"><strong>OS em andamento</strong><span>No momento</span></div><div class="operation-value">2</div></div>
            <div class="operation-item"><div class="operation-copy"><strong>Serviços no mês</strong><span>Produtividade</span></div><div class="operation-value">21</div></div>
        </div>
    </div>
    <div class="card section-card">
        <div class="section-title"><h2>Renato Costa</h2><span class="badge success"><span class="dot"></span>Ativo</span></div>
        <p class="muted">Freios • Revisão geral</p>
        <div class="operation-list">
            <div class="operation-item"><div class="operation-copy"><strong>OS em andamento</strong><span>No momento</span></div><div class="operation-value">2</div></div>
            <div class="operation-item"><div class="operation-copy"><strong>Serviços no mês</strong><span>Produtividade</span></div><div class="operation-value">19</div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
