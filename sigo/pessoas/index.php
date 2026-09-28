<?php
declare(strict_types=1);
$paginaAtual='pessoas';
$paginaTitulo='Pessoas';
$pessoas=[
['ini'=>'MA','nome'=>'Marcos Almeida','cargo'=>'Assessoria','ativos'=>5,'pendentes'=>2],
['ini'=>'CS','nome'=>'Carla Souza','cargo'=>'Gabinete','ativos'=>4,'pendentes'=>1],
['ini'=>'PM','nome'=>'Paulo Martins','cargo'=>'Administrativo','ativos'=>3,'pendentes'=>0],
['ini'=>'AC','nome'=>'Arquivo Central','cargo'=>'Arquivo','ativos'=>126,'pendentes'=>0],
['ini'=>'RF','nome'=>'Renata Ferreira','cargo'=>'Assessoria','ativos'=>2,'pendentes'=>1],
['ini'=>'JL','nome'=>'João Lima','cargo'=>'Gabinete','ativos'=>1,'pendentes'=>0],
];
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIGO - Pessoas</title>
<link rel="stylesheet" href="../assets/css/style.css?v=20260928-4"><link rel="stylesheet" href="../assets/css/pages.css?v=20260928-1">
</head><body>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/topbar.php'; ?>
<div class="app-body"><?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="module-page">
<section class="module-head"><div><span class="module-kicker">Responsáveis</span><h1>Pessoas</h1><p>Gerencie quem pode receber documentos e acompanhe a carga atual de cada responsável.</p></div><div class="module-actions"><button class="primary-button"><i data-lucide="user-plus"></i>Nova pessoa</button></div></section>
<div class="toolbar"><label class="toolbar-search"><i data-lucide="search"></i><input type="search" placeholder="Buscar pessoa ou setor" data-live-search="[data-person]"></label></div>
<section class="people-grid">
<?php foreach($pessoas as $p): ?>
<article class="person-card" data-person data-search="<?= htmlspecialchars(strtolower($p['nome'].' '.$p['cargo']),ENT_QUOTES,'UTF-8') ?>">
<div class="person-head"><span class="person-avatar-lg"><?= htmlspecialchars($p['ini']) ?></span><div><strong><?= htmlspecialchars($p['nome']) ?></strong><span><?= htmlspecialchars($p['cargo']) ?></span></div></div>
<div class="person-metrics"><div><small>Ofícios atuais</small><strong><?= (int)$p['ativos'] ?></strong></div><div><small>Aguardando confirmação</small><strong><?= (int)$p['pendentes'] ?></strong></div></div>
</article>
<?php endforeach; ?>
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