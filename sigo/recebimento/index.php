<?php
declare(strict_types=1);
$paginaAtual='recebimento';
$paginaTitulo='Recebimento rápido';
$pendentes=[
['prot'=>'1146/2026','oficio'=>'0406/2026','origem'=>'SEMAS','assunto'=>'Manutenção do sistema de ar-condicionado','hora'=>'09:52'],
['prot'=>'1142/2026','oficio'=>'0294/2026','origem'=>'SEMED','assunto'=>'Encaminhamento de expediente','hora'=>'09:14'],
['prot'=>'1138/2026','oficio'=>'0611/2026','origem'=>'SEINFRA','assunto'=>'Solicitação de manifestação','hora'=>'08:32'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIGO - Recebimento rápido</title>
<link rel="stylesheet" href="../assets/css/style.css?v=20260928-4"><link rel="stylesheet" href="../assets/css/pages.css?v=20260928-1">
</head><body>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/topbar.php'; ?>
<div class="app-body"><?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="module-page">
<section class="module-head"><div><span class="module-kicker">Confirmação</span><h1>Recebimento rápido</h1><p>A pessoa informa quem é, seleciona os ofícios recebidos e confirma de uma só vez.</p></div></section>
<section class="receipt-layout">
<aside class="identity-card">
<h2>Identifique-se</h2><p>Na versão final, o sistema lembrará a pessoa neste dispositivo após a primeira identificação segura.</p>
<div class="field"><label>Seu nome</label><select><option>Selecione seu nome</option><option>Marcos Almeida</option><option>Carla Souza</option><option>Paulo Martins</option></select></div>
<div class="field" style="margin-top:10px"><label>PIN de acesso</label><input type="password" maxlength="6" placeholder="••••••"></div>
<button class="primary-button" type="button" style="margin-top:14px;width:100%">Continuar</button>
</aside>
<section class="page-card">
<div class="card-title-row"><div><h2>Ofícios aguardando confirmação</h2><p>Marque somente os documentos que você recebeu fisicamente.</p></div><label><input type="checkbox" data-select-all> <span style="font-size:.62rem;color:var(--muted)">Selecionar todos</span></label></div>
<div class="receipt-list">
<?php foreach($pendentes as $p): ?>
<label class="receipt-item"><input type="checkbox" data-receipt-check><span class="receipt-item-copy"><strong><?= htmlspecialchars($p['oficio']) ?> · Protocolo <?= htmlspecialchars($p['prot']) ?></strong><span><?= htmlspecialchars($p['origem']) ?> · <?= htmlspecialchars($p['assunto']) ?></span></span><time><?= htmlspecialchars($p['hora']) ?></time></label>
<?php endforeach; ?>
</div>
<div class="module-actions" style="margin-top:16px;justify-content:flex-end"><button class="primary-button" type="button" data-confirm-receipt><i data-lucide="circle-check"></i>Confirmar selecionados</button></div>
</section>
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