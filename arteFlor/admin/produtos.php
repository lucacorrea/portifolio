<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'produtos';
$produtos = load_json('produtos.json');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Produtos | Arte&Flor</title>
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
        <h1>Produtos</h1>
        <p>Gerencie produtos, preços, estoque, status, destaque e exibição no catálogo público.</p>
      </div>
      <div class="admin-hero-actions">
        <a class="btn btn-soft" href="categorias.php">Categorias</a>
        <a class="btn btn-primary" href="produto-form.php">Cadastrar produto</a>
      </div>
    </section>

    <section class="admin-command-bar">
      <label class="admin-field"><span>Buscar produto</span><input type="search" placeholder="Nome, categoria, tag ou SKU"></label>
      <label class="admin-field"><span>Categoria</span><select><option>Todas</option><option>Buquês</option><option>Arranjos</option><option>Vasos</option><option>Presentes</option></select></label>
      <label class="admin-field"><span>Status</span><select><option>Todos</option><option>Disponível</option><option>Sob encomenda</option><option>Inativo</option></select></label>
      <label class="admin-field"><span>Estoque</span><select><option>Todos</option><option>Baixo estoque</option><option>Sem estoque</option><option>Com estoque</option></select></label>
      <button class="btn btn-soft" type="button">Filtrar</button>
    </section>

    <section class="admin-kpi-grid">
      <article class="admin-kpi-card"><span>Total de produtos</span><strong><?= count($produtos) ?></strong><small>Catálogo mockado</small></article>
      <article class="admin-kpi-card"><span>Disponíveis</span><strong><?= count(array_filter($produtos, fn($p) => ($p['status'] ?? '') === 'disponivel')) ?></strong><small>Prontos para venda</small></article>
      <article class="admin-kpi-card"><span>Sob encomenda</span><strong><?= count(array_filter($produtos, fn($p) => !empty($p['sob_encomenda']))) ?></strong><small>Pedido personalizado</small></article>
      <article class="admin-kpi-card"><span>Estoque baixo</span><strong>3</strong><small>Atenção operacional</small></article>
    </section>

    <div class="admin-data-table">
      <table>
        <thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Estoque</th><th>Destaque</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($produtos as $p): ?>
          <tr>
            <td>
              <div class="admin-avatar-line">
                <span class="admin-avatar"><?= e($p['imagens'][0] ?? '💐') ?></span>
                <div class="admin-item-title"><strong><?= e($p['nome']) ?></strong><small><?= e($p['descricao_curta'] ?? '') ?></small></div>
              </div>
            </td>
            <td><?= e($p['categoria']) ?></td>
            <td><?= !empty($p['preco']) ? money_br($p['preco_promocional'] ?: $p['preco']) : 'Consultar' ?></td>
            <td><span class="<?= ($p['status'] ?? '') === 'disponivel' ? 'admin-badge-ok' : 'admin-badge-warn' ?>"><?= e($p['status'] ?? 'indefinido') ?></span></td>
            <td><?= (int)($p['estoque'] ?? 0) ?></td>
            <td><span class="<?= !empty($p['destaque']) ? 'admin-badge-soft' : 'admin-badge-info' ?>"><?= !empty($p['destaque']) ? 'Sim' : 'Normal' ?></span></td>
            <td>
              <div class="admin-table-actions">
                <a href="../produto.php?slug=<?= e($p['slug'] ?? '') ?>">Ver</a>
                <a href="produto-form.php">Editar</a>
                <button type="button">Duplicar</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
