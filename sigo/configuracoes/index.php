<?php
declare(strict_types=1);
$paginaAtual='configuracoes';
$paginaTitulo='Configurações';
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIGO - Configurações</title>
<link rel="stylesheet" href="../assets/css/style.css?v=20260928-4"><link rel="stylesheet" href="../assets/css/pages.css?v=20260928-1">
</head><body>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/topbar.php'; ?>
<div class="app-body"><?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="module-page">
<section class="module-head"><div><span class="module-kicker">Sistema</span><h1>Configurações</h1><p>Ajustes gerais do SIGO e regras do fluxo interno da Casa Civil.</p></div><div class="module-actions"><button class="primary-button"><i data-lucide="save"></i>Salvar alterações</button></div></section>
<section class="settings-grid">
<article class="page-card">
<div class="card-title-row"><div><h2>Fluxo de documentos</h2><p>Regras de acompanhamento e confirmação.</p></div></div>
<div class="setting-row"><div><strong>Exigir confirmação de recebimento</strong><span>Responsáveis precisam confirmar que receberam o documento.</span></div><label class="switch"><input type="checkbox" checked><span></span></label></div>
<div class="setting-row"><div><strong>Alertar após 24 horas</strong><span>Destaca ofícios encaminhados sem confirmação.</span></div><label class="switch"><input type="checkbox" checked><span></span></label></div>
<div class="setting-row"><div><strong>Registrar localização física</strong><span>Exige um local ao cadastrar ou movimentar o documento.</span></div><label class="switch"><input type="checkbox" checked><span></span></label></div>
<div class="setting-row"><div><strong>Permitir PIN no recebimento rápido</strong><span>Libera confirmação sem login completo.</span></div><label class="switch"><input type="checkbox" checked><span></span></label></div>
</article>
<aside>
<article class="helper-card"><h3>Identidade do sistema</h3><div class="field"><label>Nome</label><input value="SIGO"></div><div class="field" style="margin-top:10px"><label>Órgão</label><input value="Casa Civil"></div></article>
<article class="helper-card"><h3>Segurança</h3><p>Na etapa do backend vamos separar permissões entre administrador, acompanhamento e recebimento rápido.</p></article>
</aside>
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