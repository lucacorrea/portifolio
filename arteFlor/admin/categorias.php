<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'categorias';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Categorias | Arte&Flor</title>
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
        <span class="badge">Catálogo</span>
        <h1>Categorias</h1>
        <p>Organize o catálogo por grupos comerciais para facilitar a navegação do cliente.</p>
      </div>
      <div class="admin-hero-actions">
        <a class="btn btn-primary" href="categoria-form.php">Cadastrar categoria</a>
      </div>
    </section>

    <section class="admin-command-bar">
      <label class="admin-field"><span>Buscar</span><input placeholder="Nome ou descrição"></label>
      <label class="admin-field"><span>Status</span><select><option>Todos</option><option>Ativa</option><option>Oculta</option></select></label>
      <label class="admin-field"><span>Destaque</span><select><option>Todos</option><option>Com destaque</option><option>Sem destaque</option></select></label>
      <label class="admin-field"><span>Ordenação</span><select><option>Mais usadas</option><option>Alfabética</option><option>Mais recentes</option></select></label>
      <button class="btn btn-soft" type="button">Filtrar</button>
    </section>

    <section class="admin-kpi-grid">
      <article class="admin-kpi-card"><span>Categorias</span><strong>8</strong><small>6 ativas</small></article>
      <article class="admin-kpi-card"><span>Mais usada</span><strong>Buquês</strong><small>24 produtos</small></article>
      <article class="admin-kpi-card"><span>Ocultas</span><strong>2</strong><small>Em revisão</small></article>
      <article class="admin-kpi-card"><span>Destaques</span><strong>4</strong><small>Home e catálogo</small></article>
    </section>

    <div class="admin-data-table">
      <table>
        <thead><tr><th>Categoria</th><th>Descrição</th><th>Produtos</th><th>Status</th><th>Destaque</th><th>Ações</th></tr></thead>
        <tbody>
          <tr><td><div class="admin-avatar-line"><span class="admin-avatar">💐</span><div class="admin-item-title"><strong>Buquês</strong><small>buques</small></div></div></td><td>Buquês naturais e personalizados</td><td>24</td><td><span class="admin-badge-ok">Ativa</span></td><td><span class="admin-badge-soft">Home</span></td><td><div class="admin-table-actions"><a href="categoria-form.php">Editar</a><button>Ocultar</button></div></td></tr>
          <tr><td><div class="admin-avatar-line"><span class="admin-avatar">🌺</span><div class="admin-item-title"><strong>Arranjos</strong><small>arranjos</small></div></div></td><td>Composições florais para ocasiões especiais</td><td>16</td><td><span class="admin-badge-ok">Ativa</span></td><td><span class="admin-badge-soft">Catálogo</span></td><td><div class="admin-table-actions"><a href="categoria-form.php">Editar</a><button>Ocultar</button></div></td></tr>
          <tr><td><div class="admin-avatar-line"><span class="admin-avatar">🪴</span><div class="admin-item-title"><strong>Vasos</strong><small>vasos</small></div></div></td><td>Plantas e vasos decorativos</td><td>12</td><td><span class="admin-badge-ok">Ativa</span></td><td><span class="admin-badge-info">Normal</span></td><td><div class="admin-table-actions"><a href="categoria-form.php">Editar</a><button>Ocultar</button></div></td></tr>
          <tr><td><div class="admin-avatar-line"><span class="admin-avatar">🎁</span><div class="admin-item-title"><strong>Presentes</strong><small>presentes</small></div></div></td><td>Cestas, cartões e kits especiais</td><td>9</td><td><span class="admin-badge-warn">Oculta</span></td><td><span class="admin-badge-soft">Home</span></td><td><div class="admin-table-actions"><a href="categoria-form.php">Editar</a><button>Ativar</button></div></td></tr>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
