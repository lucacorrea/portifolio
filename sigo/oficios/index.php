<?php
declare(strict_types=1);

$paginaAtual = 'oficios';
$paginaTitulo = 'Ofícios';

$oficios = [
    ['protocolo'=>'1146/2026','oficio'=>'0406/2026','origem'=>'SEMAS','assunto'=>'Manutenção do sistema de ar-condicionado','responsavel'=>'Marcos Almeida','local'=>'Gabinete','status'=>'Aguardando recebimento','classe'=>'pending','data'=>'28/09/2026'],
    ['protocolo'=>'1145/2026','oficio'=>'0189/2026','origem'=>'SEMED','assunto'=>'Solicitação de apoio institucional','responsavel'=>'Carla Souza','local'=>'Assessoria','status'=>'Em andamento','classe'=>'progress','data'=>'28/09/2026'],
    ['protocolo'=>'1144/2026','oficio'=>'0721/2026','origem'=>'SEINFRA','assunto'=>'Encaminhamento de documentação técnica','responsavel'=>'Paulo Martins','local'=>'Gabinete','status'=>'Recebido','classe'=>'received','data'=>'28/09/2026'],
    ['protocolo'=>'1143/2026','oficio'=>'0312/2026','origem'=>'SEFAZ','assunto'=>'Informações administrativas','responsavel'=>'Arquivo Central','local'=>'Arquivo Central','status'=>'Arquivado','classe'=>'archived','data'=>'27/09/2026'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIGO - Ofícios</title>
<link rel="stylesheet" href="../assets/css/style.css?v=20260928-4">
<link rel="stylesheet" href="../assets/css/pages.css?v=20260928-1">
</head>
<body>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/topbar.php'; ?>
<div class="app-body">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="module-page">
<section class="module-head">
<div><span class="module-kicker">Documentos</span><h1>Ofícios</h1><p>Consulte rapidamente onde cada documento está e quem é o responsável atual.</p></div>
<div class="module-actions">
<button class="ghost-button" type="button"><i data-lucide="download"></i>Exportar</button>
<a class="primary-button" href="cadastrar.php"><i data-lucide="plus"></i>Novo ofício</a>
</div>
</section>

<section class="stats-row">
<article class="module-stat"><div class="stat-top"><small>Total ativo</small><span class="stat-icon"><i data-lucide="files"></i></span></div><strong>30</strong></article>
<article class="module-stat"><div class="stat-top"><small>Aguardando recebimento</small><span class="stat-icon"><i data-lucide="clock-3"></i></span></div><strong>5</strong></article>
<article class="module-stat"><div class="stat-top"><small>Em andamento</small><span class="stat-icon"><i data-lucide="workflow"></i></span></div><strong>18</strong></article>
<article class="module-stat"><div class="stat-top"><small>Arquivados</small><span class="stat-icon"><i data-lucide="archive"></i></span></div><strong>126</strong></article>
</section>

<div class="toolbar">
<label class="toolbar-search"><i data-lucide="search"></i><input type="search" placeholder="Buscar protocolo, ofício, origem ou responsável" data-live-search="[data-filter-row]"></label>
<select class="toolbar-select" data-status-select>
<option value="all">Todas as situações</option><option value="pending">Aguardando</option><option value="progress">Em andamento</option><option value="received">Recebidos</option><option value="archived">Arquivados</option>
</select>
</div>

<section class="page-card">
<div class="card-title-row"><div><h2>Todos os ofícios</h2><p>Lista atual de documentos controlados pelo SIGO.</p></div></div>
<div class="data-table-wrap">
<table class="data-table">
<thead><tr><th>Protocolo</th><th>Ofício</th><th>Origem</th><th>Assunto</th><th>Responsável</th><th>Local</th><th>Situação</th><th>Data</th></tr></thead>
<tbody>
<?php foreach($oficios as $item): ?>
<tr data-filter-row data-status="<?= htmlspecialchars($item['classe'],ENT_QUOTES,'UTF-8') ?>" data-search="<?= htmlspecialchars(strtolower(implode(' ',$item)),ENT_QUOTES,'UTF-8') ?>">
<td><a class="table-link" href="#"><?= htmlspecialchars($item['protocolo']) ?></a></td>
<td><strong><?= htmlspecialchars($item['oficio']) ?></strong></td>
<td><?= htmlspecialchars($item['origem']) ?></td>
<td><?= htmlspecialchars($item['assunto']) ?></td>
<td><?= htmlspecialchars($item['responsavel']) ?></td>
<td><?= htmlspecialchars($item['local']) ?></td>
<td><span class="status-pill status-<?= htmlspecialchars($item['classe']) ?>"><?= htmlspecialchars($item['status']) ?></span></td>
<td><?= htmlspecialchars($item['data']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
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