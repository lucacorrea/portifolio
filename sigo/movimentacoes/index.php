<?php
declare(strict_types=1);
$paginaAtual='movimentacoes';
$paginaTitulo='Movimentações';
$eventos=[
['icon'=>'file-plus-2','title'=>'Ofício cadastrado','text'=>'Protocolo 1146/2026 cadastrado no SIGO.','time'=>'Hoje, 09:31'],
['icon'=>'send','title'=>'Encaminhado para Marcos Almeida','text'=>'Ofício 0406/2026 saiu da administração e aguarda confirmação.','time'=>'Hoje, 09:52'],
['icon'=>'circle-check','title'=>'Recebimento confirmado','text'=>'Paulo Martins confirmou o recebimento do Ofício 0721/2026.','time'=>'Hoje, 10:18'],
['icon'=>'archive','title'=>'Documento arquivado','text'=>'Ofício 0312/2026 foi enviado ao Arquivo Central.','time'=>'Ontem, 16:42'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIGO - Movimentações</title>
<link rel="stylesheet" href="../assets/css/style.css?v=20260928-4"><link rel="stylesheet" href="../assets/css/pages.css?v=20260928-1">
</head><body>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/topbar.php'; ?>
<div class="app-body"><?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="module-page">
<section class="module-head"><div><span class="module-kicker">Rastreamento</span><h1>Movimentações</h1><p>Acompanhe cada mudança de responsável, local e situação dos documentos.</p></div><div class="module-actions"><button class="ghost-button"><i data-lucide="download"></i>Exportar histórico</button></div></section>
<section class="stats-row">
<article class="module-stat"><div class="stat-top"><small>Hoje</small><span class="stat-icon"><i data-lucide="activity"></i></span></div><strong>14</strong></article>
<article class="module-stat"><div class="stat-top"><small>Encaminhamentos</small><span class="stat-icon"><i data-lucide="send"></i></span></div><strong>7</strong></article>
<article class="module-stat"><div class="stat-top"><small>Confirmações</small><span class="stat-icon"><i data-lucide="circle-check"></i></span></div><strong>5</strong></article>
<article class="module-stat"><div class="stat-top"><small>Arquivamentos</small><span class="stat-icon"><i data-lucide="archive"></i></span></div><strong>2</strong></article>
</section>
<section class="page-card">
<div class="card-title-row"><div><h2>Linha do tempo</h2><p>Movimentações mais recentes registradas.</p></div></div>
<div class="timeline-list">
<?php foreach($eventos as $e): ?>
<div class="timeline-row"><span class="timeline-mark"><i data-lucide="<?= $e['icon'] ?>"></i></span><strong><?= htmlspecialchars($e['title']) ?></strong><p><?= htmlspecialchars($e['text']) ?></p><time><?= htmlspecialchars($e['time']) ?></time></div>
<?php endforeach; ?>
</div>
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