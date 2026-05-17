<?php require_once __DIR__ . '/../includes/helpers.php'; $produtos = load_json('produtos.json'); ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Listagem de produtos | Arte&Flor</title>
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-header">
      <div>
        <span class="badge">Produtos</span>
        <h1 class="section-title">Listagem de produtos</h1>
        <p class="muted">Tela separada para visualizar, filtrar e gerenciar os produtos cadastrados.</p>
      </div>
      <a class="btn btn-primary" href="produto-form.php">Cadastrar novo produto</a>
    </div>

    <div class="admin-tabs">
      <a class="active" href="produtos.php">Listagem</a>
      <a href="produto-form.php">Cadastro</a>
      <a href="estoque.php">Movimentações</a>
    </div>

    <section class="admin-filter-card card">
      <div class="form-grid">
        <label class="form-group"><span>Buscar produto</span><input type="search" placeholder="Nome, categoria ou tag"></label>
        <label class="form-group"><span>Categoria</span><select><option>Todas</option><option>Buquês</option><option>Arranjos</option><option>Vasos</option><option>Presentes</option></select></label>
        <label class="form-group"><span>Status</span><select><option>Todos</option><option>Disponível</option><option>Sob encomenda</option><option>Inativo</option></select></label>
        <label class="form-group"><span>Estoque</span><select><option>Todos</option><option>Estoque baixo</option><option>Sem estoque</option><option>Com estoque</option></select></label>
      </div>
    </section>

    <section class="grid-4" style="margin:22px 0">
      <div class="card kpi"><span>Total de produtos</span><strong><?= count($produtos) ?></strong></div>
      <div class="card kpi"><span>Disponíveis</span><strong><?= count(array_filter($produtos, fn($p) => $p['status'] === 'disponivel')) ?></strong></div>
      <div class="card kpi"><span>Sob encomenda</span><strong><?= count(array_filter($produtos, fn($p) => !empty($p['sob_encomenda']))) ?></strong></div>
      <div class="card kpi"><span>Estoque baixo</span><strong>3</strong></div>
    </section>

    <div class="table-wrap">
      <table>
        <tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Estoque</th><th>Ações</th></tr>
        <?php foreach ($produtos as $p): ?>
          <tr>
            <td>
              <strong><?= e($p['nome']) ?></strong><br>
              <small class="muted"><?= e($p['descricao_curta']) ?></small>
            </td>
            <td><?= e($p['categoria']) ?></td>
            <td><?= $p['preco'] ? money_br($p['preco']) : 'Consultar' ?></td>
            <td><span class="status"><?= e($p['status']) ?></span></td>
            <td><?= (int)$p['estoque'] ?></td>
            <td>
              <div class="table-actions">
                <a href="../produto.php?slug=<?= e($p['slug']) ?>">Ver</a>
                <a href="produto-form.php">Editar</a>
                <button type="button">Remover</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </main>
</div>
</body>
</html>
