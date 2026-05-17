<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'cupom-form';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cadastrar cupom | Arte&Flor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
  <link rel="stylesheet" href="../assets/css/admin-premium.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body class="admin-premium-body">
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <section class="admin-page-hero">
      <div class="admin-page-title">
        <span class="badge">Cadastro</span>
        <h1>Cadastrar cupom</h1>
        <p>Crie uma ação comercial para aplicar desconto no catálogo.</p>
      </div>
      <div class="admin-hero-actions"><a class="btn btn-soft" href="cupons.php">Voltar para cupons</a></div>
    </section>

    <form class="admin-form-shell">
      <section class="admin-form-card">
        <div class="admin-form-section">
          <div class="admin-section-title"><strong>Informações principais</strong><p>Dados básicos da ação comercial.</p></div>
          <div class="admin-form-grid">
            <label class="admin-field"><span>Código</span><input placeholder="Ex: FLOR15"></label>
            <label class="admin-field"><span>Nome da ação</span><input placeholder="Ex: Primeira compra"></label>
            <label class="admin-field"><span>Tipo</span><select><option>Percentual</option><option>Valor fixo</option></select></label>
            <label class="admin-field"><span>Valor</span><input type="number" placeholder="15"></label>
            <label class="admin-field"><span>Início</span><input type="date"></label>
            <label class="admin-field"><span>Fim</span><input type="date"></label>
            <label class="admin-field full"><span>Observações</span><textarea placeholder="Regras internas para uso"></textarea></label>
          </div>
        </div>

        <div class="admin-form-section">
          <div class="admin-section-title"><strong>Aplicação</strong><p>Configuração visual da campanha.</p></div>
          <div class="admin-form-grid">
            <label class="admin-field"><span>Status</span><select><option>Ativo</option><option>Pausado</option><option>Encerrado</option></select></label>
            <label class="admin-field"><span>Canal</span><select><option>Catálogo</option><option>Atendimento</option><option>Todos</option></select></label>
          </div>
        </div>
      </section>

      <aside class="admin-form-card admin-side-card">
        <div class="admin-preview-image">🎟️</div>
        <div class="admin-check-list">
          <label><input type="checkbox" checked> Exibir no checkout</label>
          <label><input type="checkbox" checked> Disponível no catálogo</label>
          <label><input type="checkbox"> Aplicar somente em produtos selecionados</label>
        </div>
        <button class="btn btn-primary" type="button">Salvar demonstração</button>
        <p class="muted">Na versão final, esta tela será conectada às regras reais do sistema.</p>
      </aside>
    </form>
  </main>
</div>
</body>
</html>
