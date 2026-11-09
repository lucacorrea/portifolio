<?php
declare(strict_types=1);
$paginaAtual='oficios';
$paginaTitulo='Novo ofício';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIGO - Novo Ofício</title>
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
<div><span class="module-kicker">Cadastro</span><h1>Novo ofício</h1><p>Registre o documento já protocolado e defina sua primeira movimentação.</p></div>
<div class="module-actions"><a class="ghost-button" href="index.php"><i data-lucide="arrow-left"></i>Voltar</a></div>
</section>

<form class="form-shell" data-office-form>
<section class="form-card">
<div class="form-section">
<div class="form-section-head"><h2>Identificação do documento</h2><p>Dados principais do ofício recebido pela Casa Civil.</p></div>
<div class="form-grid">
<div class="field"><label>Número do protocolo</label><input required placeholder="Ex.: 1146/2026"></div>
<div class="field"><label>Número do ofício</label><input required placeholder="Ex.: 0406/2026"></div>
<div class="field"><label>Órgão / Secretaria de origem</label><input required placeholder="Ex.: SEMAS"></div>
<div class="field"><label>Data do ofício</label><input type="date"></div>
<div class="field span-2"><label>Assunto</label><input required placeholder="Resumo do assunto do documento"></div>
</div>
</div>

<div class="form-section">
<div class="form-section-head"><h2>Entrada na Casa Civil</h2><p>Informações do protocolo físico e do recebimento interno.</p></div>
<div class="form-grid">
<div class="field"><label>Data de recebimento</label><input type="date"></div>
<div class="field"><label>Hora de recebimento</label><input type="time"></div>
<div class="field"><label>Quantidade de folhas</label><input type="number" min="1" value="1"></div>
<div class="field"><label>Recebido por</label><input placeholder="Nome da pessoa que entregou ao administrador"></div>
</div>
</div>

<div class="form-section">
<div class="form-section-head"><h2>Encaminhamento inicial</h2><p>Defina quem ficará responsável e onde o documento físico estará.</p></div>
<div class="form-grid">
<div class="field"><label>Responsável</label><select><option>Selecione</option><option>Marcos Almeida</option><option>Carla Souza</option><option>Paulo Martins</option></select></div>
<div class="field"><label>Local atual</label><select><option>Gabinete</option><option>Assessoria</option><option>Arquivo Central</option><option>Outro</option></select></div>
<div class="field span-2"><label>Observações</label><textarea placeholder="Informações adicionais sobre o documento ou encaminhamento"></textarea></div>
</div>
</div>

<div class="form-section">
<div class="module-actions">
<button class="ghost-button" type="button">Salvar rascunho</button>
<button class="primary-button" type="submit"><i data-lucide="save"></i>Cadastrar ofício</button>
</div>
</div>
</section>

<aside>
<article class="helper-card"><h3>Anexo digitalizado</h3><label class="dropzone"><i data-lucide="upload-cloud"></i><strong>Adicionar PDF ou imagem</strong><span>O upload real será ligado ao backend depois.</span><input type="file" hidden></label></article>
<article class="helper-card"><h3>O que será registrado</h3><ul class="helper-list"><li>Cadastro do ofício</li><li>Responsável inicial</li><li>Localização física</li><li>Primeira movimentação no histórico</li></ul></article>
</aside>
</form>
</main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/searchOverlay.php'; ?>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="../assets/js/app.js?v=20260928-4"></script>
<script src="../assets/js/pages.js?v=20260928-1"></script>
</body></html>