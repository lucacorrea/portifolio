<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'categoria-form';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cadastrar categoria | Arte&Flor</title>
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
        <h1>Cadastrar categoria</h1>
        <p>Crie uma categoria para organizar os produtos do catálogo da Arte&Flor.</p>
      </div>
      <div class="admin-hero-actions"><a class="btn btn-soft" href="categorias.php">Voltar para categorias</a></div>
    </section>

    <form class="admin-form-shell">
      <section class="admin-form-card">
        <div class="admin-form-section">
          <div class="admin-section-title"><strong>Dados principais</strong><p>Informações de exibição da categoria.</p></div>
          <div class="admin-form-grid">
            <label class="admin-field"><span>Nome</span><input placeholder="Ex: Buquês" required></label>
            <label class="admin-field"><span>Slug</span><input placeholder="buques"></label>
            <label class="admin-field"><span>Ícone</span><input placeholder="💐"></label>
            <label class="admin-field"><span>Status</span><select><option>Ativa</option><option>Inativa</option></select></label>
            <label class="admin-field full"><span>Descrição curta</span><input placeholder="Resumo para aparecer no catálogo"></label>
            <label class="admin-field full"><span>Observações internas</span><textarea placeholder="Notas sobre esta categoria"></textarea></label>
          </div>
        </div>

        <div class="admin-form-section">
          <div class="admin-section-title"><strong>Configuração visual</strong><p>Organização para home e catálogo.</p></div>
          <div class="admin-form-grid">
            <label class="admin-field"><span>Ordem</span><input type="number" value="1"></label>
            <label class="admin-field"><span>Cor de apoio</span><input type="text" value="#4F8F6B"></label>
          </div>
        </div>
      </section>

      <aside class="admin-form-card admin-side-card">
        <div class="admin-preview-image">💐</div>
        <div class="admin-check-list">
          <label><input type="checkbox" checked> Exibir no catálogo</label>
          <label><input type="checkbox" checked> Mostrar na página inicial</label>
          <label><input type="checkbox"> Categoria promocional</label>
          <label><input type="checkbox"> Priorizar na listagem</label>
        </div>
        <button class="btn btn-primary" type="button">Salvar demonstração</button>
        <p class="muted">Na versão real, essa tela será conectada ao banco de dados.</p>
      </aside>
    </form>
  </main>
</div>
</body>
</html>
