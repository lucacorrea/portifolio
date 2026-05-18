<?php
$adminTitle = 'Produtos';
$activeAdmin = 'produtos';
require_once __DIR__ . '/../includes/admin-head.php';
$produtos = load_json('produtos.json');
$disponiveis = count(array_filter($produtos, fn($p) => ($p['status'] ?? '') === 'disponivel'));
$encomendas = count(array_filter($produtos, fn($p) => !empty($p['sob_encomenda'])));
$destaques = count(array_filter($produtos, fn($p) => !empty($p['destaque'])));
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Catálogo</span>
    <h1>Produtos</h1>
    <p>Listagem separada do cadastro, com filtros, KPIs e ações operacionais do catálogo.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/categorias.php') ?>">Categorias</a>
    <a class="btn btn-primary" href="<?= site_url('admin/produto-form.php') ?>">Cadastrar produto</a>
  </div>
</section>

<section class="admin-command-bar">
  <label class="admin-field"><span>Buscar</span><input type="search" placeholder="Nome, categoria, SKU ou tag"></label>
  <label class="admin-field"><span>Categoria</span><select><option>Todas</option><option>Buquês</option><option>Arranjos</option><option>Vasos</option><option>Presentes</option></select></label>
  <label class="admin-field"><span>Status</span><select><option>Todos</option><option>Disponível</option><option>Sob encomenda</option><option>Inativo</option></select></label>
  <label class="admin-field"><span>Estoque</span><select><option>Todos</option><option>Baixo estoque</option><option>Sem estoque</option><option>Com estoque</option></select></label>
  <button class="btn btn-soft" type="button">Filtrar</button>
</section>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Total</span><strong><?= count($produtos) ?></strong><small>Produtos mockados</small></article>
  <article class="admin-kpi-card"><span>Disponíveis</span><strong><?= $disponiveis ?></strong><small>Venda online visual</small></article>
  <article class="admin-kpi-card"><span>Sob encomenda</span><strong><?= $encomendas ?></strong><small>Curadoria manual</small></article>
  <article class="admin-kpi-card"><span>Destaques</span><strong><?= $destaques ?></strong><small>Home e catálogo</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Destaque</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach ($produtos as $p): ?>
      <?php $image = first_image($p); $status = (string) ($p['status'] ?? 'disponivel'); ?>
      <tr>
        <td>
          <div class="admin-avatar-line">
            <span class="admin-avatar image-avatar">
              <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"><?php else: ?>A&F<?php endif; ?>
            </span>
            <div class="admin-item-title"><strong><?= e($p['nome']) ?></strong><small><?= e($p['sku'] ?? $p['slug']) ?></small></div>
          </div>
        </td>
        <td><?= e($p['categoria']) ?></td>
        <td><?= effective_price($p) > 0 ? money_br(effective_price($p)) : 'Sob consulta' ?></td>
        <td><?= (int) ($p['estoque'] ?? 0) ?></td>
        <td><span class="<?= $status === 'disponivel' ? 'admin-badge-ok' : 'admin-badge-warn' ?>"><?= e(status_label($status)) ?></span></td>
        <td><span class="<?= !empty($p['destaque']) ? 'admin-badge-soft' : 'admin-badge-info' ?>"><?= !empty($p['destaque']) ? 'Sim' : 'Normal' ?></span></td>
        <td>
          <div class="admin-table-actions">
            <a href="<?= site_url('produto.php?slug=' . rawurlencode($p['slug'] ?? '')) ?>">Ver</a>
            <a href="<?= site_url('admin/produto-form.php') ?>">Editar</a>
            <button type="button">Duplicar</button>
            <button type="button">Inativar</button>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
