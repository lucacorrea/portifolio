<?php
declare(strict_types=1);
$paginaAtual='relatorios';
$paginaTitulo='Relatórios';
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIGO - Relatórios</title>
<link rel="stylesheet" href="../assets/css/style.css?v=20260928-4"><link rel="stylesheet" href="../assets/css/pages.css?v=20260928-1">
</head><body>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/topbar.php'; ?>
<div class="app-body"><?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="module-page">
<section class="module-head"><div><span class="module-kicker">Análise</span><h1>Relatórios</h1><p>Veja volume de documentos, pendências, responsáveis e localização física.</p></div><div class="module-actions"><button class="ghost-button"><i data-lucide="download"></i>Exportar relatório</button></div></section>
<section class="stats-row">
<article class="module-stat"><div class="stat-top"><small>Recebidos no mês</small><span class="stat-icon"><i data-lucide="inbox"></i></span></div><strong>148</strong></article>
<article class="module-stat"><div class="stat-top"><small>Concluídos</small><span class="stat-icon"><i data-lucide="check-circle-2"></i></span></div><strong>126</strong></article>
<article class="module-stat"><div class="stat-top"><small>Pendentes</small><span class="stat-icon"><i data-lucide="clock-3"></i></span></div><strong>22</strong></article>
<article class="module-stat"><div class="stat-top"><small>Tempo médio</small><span class="stat-icon"><i data-lucide="timer"></i></span></div><strong>2,4 dias</strong></article>
</section>
<section class="report-grid">
<article class="page-card"><div class="card-title-row"><div><h2>Movimentações por semana</h2><p>Volume das últimas seis semanas.</p></div></div><div class="report-bars">
<?php foreach([62,78,55,88,72,94] as $i=>$v): ?><div class="report-bar-column"><span class="report-bar" style="--value:<?= $v ?>%"></span><small>Sem <?= $i+1 ?></small></div><?php endforeach; ?>
</div></article>
<article class="page-card"><div class="card-title-row"><div><h2>Por localização</h2><p>Onde os documentos físicos estão.</p></div></div><div class="distribution-list">
<?php foreach([['Arquivo Central',62],['Gabinete',19],['Assessoria',12],['Outros',7]] as $d): ?><div class="distribution-row"><div class="distribution-meta"><span><?= $d[0] ?></span><strong><?= $d[1] ?>%</strong></div><div class="distribution-track"><span style="--value:<?= $d[1] ?>%"></span></div></div><?php endforeach; ?>
</div></article>
</section>
</main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/searchOverlay.php'; ?>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="../assets/js/app.js?v=20260928-4"></script>
<script src="../assets/js/pages.js?v=20260928-1"></script>
</body></html>