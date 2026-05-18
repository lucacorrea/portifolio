<?php
$adminTitle = 'Categorias';
$activeAdmin = 'categorias';
require_once __DIR__ . '/../includes/admin-head.php';
$produtos = load_json('produtos.json');
$categorias = [
    ['nome' => 'Buquês', 'slug' => 'buques', 'descricao' => 'Buquês naturais e personalizados', 'status' => 'Ativa', 'destaque' => 'Home', 'cor' => '#4F8F6B'],
    ['nome' => 'Arranjos', 'slug' => 'arranjos', 'descricao' => 'Composições florais para ocasiões especiais', 'status' => 'Ativa', 'destaque' => 'Catálogo', 'cor' => '#8A4A5B'],
    ['nome' => 'Vasos', 'slug' => 'vasos', 'descricao' => 'Plantas e vasos decorativos', 'status' => 'Ativa', 'destaque' => 'Normal', 'cor' => '#AFCBB2'],
    ['nome' => 'Presentes', 'slug' => 'presentes', 'descricao' => 'Cestas, cartões e kits especiais', 'status' => 'Ativa', 'destaque' => 'Home', 'cor' => '#F5C6D6'],
    ['nome' => 'Datas especiais', 'slug' => 'datas-especiais', 'descricao' => 'Campanhas e ocasiões sazonais', 'status' => 'Ativa', 'destaque' => 'Catálogo', 'cor' => '#B48A63'],
    ['nome' => 'Encomendas', 'slug' => 'encomendas', 'descricao' => 'Produtos personalizados sob demanda', 'status' => 'Ativa', 'destaque' => 'Normal', 'cor' => '#254736'],
];
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Catálogo</span>
    <h1>Categorias</h1>
    <p>Organize produtos por grupos comerciais, destaque e exibição na home ou catálogo.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/categoria-form.php') ?>">Cadastrar categoria</a></div>
</section>

<section class="admin-command-bar">
  <label class="admin-field"><span>Buscar</span><input placeholder="Nome ou descrição"></label>
  <label class="admin-field"><span>Status</span><select><option>Todos</option><option>Ativa</option><option>Oculta</option></select></label>
  <label class="admin-field"><span>Destaque</span><select><option>Todos</option><option>Home</option><option>Catálogo</option></select></label>
  <label class="admin-field"><span>Ordenação</span><select><option>Ordem manual</option><option>Alfabética</option><option>Mais usadas</option></select></label>
  <button class="btn btn-soft" type="button">Filtrar</button>
</section>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Categorias</span><strong><?= count($categorias) ?></strong><small>5 exibidas no catálogo</small></article>
  <article class="admin-kpi-card"><span>Mais usada</span><strong>Buquês</strong><small><?= count(array_filter($produtos, fn($p) => ($p['categoria'] ?? '') === 'Buquês')) ?> produtos</small></article>
  <article class="admin-kpi-card"><span>Destaques</span><strong>4</strong><small>Home e catálogo</small></article>
  <article class="admin-kpi-card"><span>Ocultas</span><strong>0</strong><small>Nenhuma no MVP</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Categoria</th><th>Descrição</th><th>Produtos</th><th>Status</th><th>Destaque</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($categorias as $categoria): ?>
        <?php $total = count(array_filter($produtos, fn($p) => ($p['categoria'] ?? '') === $categoria['nome'])); ?>
        <tr>
          <td>
            <div class="admin-avatar-line">
              <span class="admin-avatar color-avatar" style="--avatar-color: <?= e($categoria['cor']) ?>"></span>
              <div class="admin-item-title"><strong><?= e($categoria['nome']) ?></strong><small><?= e($categoria['slug']) ?></small></div>
            </div>
          </td>
          <td><?= e($categoria['descricao']) ?></td>
          <td><?= $total ?></td>
          <td><span class="admin-badge-ok"><?= e($categoria['status']) ?></span></td>
          <td><span class="admin-badge-soft"><?= e($categoria['destaque']) ?></span></td>
          <td><div class="admin-table-actions"><a href="<?= site_url('admin/categoria-form.php') ?>">Editar</a><button type="button">Ocultar</button></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
