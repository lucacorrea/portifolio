<?php
$adminTitle = 'Produtos';
$activeAdmin = 'produtos';
$pageScripts = ['js/products-list.js'];
require_once __DIR__ . '/../includes/products.php';
$adminUser = require_admin();

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'categoria_id' => (int) ($_GET['categoria_id'] ?? 0),
    'status' => (string) ($_GET['status'] ?? ''),
    'estoque' => (string) ($_GET['estoque'] ?? ''),
];
$produtos = product_list($filters);
$categorias = product_categories();
$stats = product_stats();
$productImagesById = product_images_by_product_ids(array_column($produtos, 'id'));
$modalProducts = array_map(static function (array $product) use ($productImagesById): array {
    $productId = (int) $product['id'];
    $images = array_map(static function (array $image): array {
        return [
            'url' => product_public_image_url($image['url'] ?? ''),
            'alt' => (string) ($image['texto_alternativo'] ?? 'Imagem do produto'),
            'principal' => !empty($image['principal']),
        ];
    }, $productImagesById[$productId] ?? []);

    if (empty($images) && !empty($product['imagem'])) {
        $images[] = [
            'url' => product_public_image_url($product['imagem']),
            'alt' => (string) ($product['nome'] ?? 'Imagem do produto'),
            'principal' => true,
        ];
    }

    return [
        'id' => $productId,
        'nome' => (string) ($product['nome'] ?? ''),
        'sku' => (string) ($product['sku'] ?? ''),
        'slug' => (string) ($product['slug'] ?? ''),
        'categoria' => (string) ($product['categoria_nome'] ?? 'Sem categoria'),
        'status' => status_label((string) ($product['status'] ?? 'disponivel')),
        'preco' => money_br((float) ($product['preco'] ?? 0)),
        'precoPromocional' => (float) ($product['preco_promocional'] ?? 0) > 0 ? money_br((float) $product['preco_promocional']) : '',
        'estoque' => (int) ($product['estoque'] ?? 0),
        'estoqueMinimo' => (int) ($product['estoque_minimo'] ?? 0),
        'descricaoCurta' => (string) ($product['descricao_curta'] ?? ''),
        'descricaoCompleta' => (string) ($product['descricao_completa'] ?? ''),
        'destaque' => !empty($product['destaque']) ? 'Sim' : 'Normal',
        'sobEncomenda' => !empty($product['sob_encomenda']) ? 'Sim' : 'Não',
        'images' => $images,
        'editUrl' => site_url('admin/produto-form.php?id=' . $productId),
    ];
}, $produtos);

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Catálogo</span>
    <h1>Produtos</h1>
    <p>Produtos salvos no banco, com filtros operacionais, imagens enviadas e ações de edição.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/categorias.php') ?>">Categorias</a>
    <a class="btn btn-primary" href="<?= site_url('admin/produto-form.php') ?>">Cadastrar produto</a>
  </div>
</section>

<?php if (isset($_GET['saved'])): ?>
  <div class="admin-alert-card admin-alert-success" role="status">
    <strong>Produto salvo</strong>
    O produto foi gravado no banco e já aparece na listagem administrativa.
  </div>
<?php endif; ?>

<form class="admin-command-bar" method="get" action="<?= site_url('admin/produtos.php') ?>">
  <label class="admin-field">
    <span>Buscar</span>
    <input name="search" type="search" value="<?= e($filters['search']) ?>" placeholder="Nome, categoria, SKU ou slug">
  </label>
  <label class="admin-field">
    <span>Categoria</span>
    <select name="categoria_id">
      <option value="0">Todas</option>
      <?php foreach ($categorias as $categoria): ?>
        <?php if ((int) $categoria['id'] <= 0) continue; ?>
        <option value="<?= (int) $categoria['id'] ?>" <?= (int) $filters['categoria_id'] === (int) $categoria['id'] ? 'selected' : '' ?>>
          <?= e($categoria['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="admin-field">
    <span>Status</span>
    <select name="status">
      <option value="">Todos</option>
      <?php foreach (product_status_options() as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="admin-field">
    <span>Estoque</span>
    <select name="estoque">
      <option value="">Todos</option>
      <option value="baixo" <?= $filters['estoque'] === 'baixo' ? 'selected' : '' ?>>Baixo estoque</option>
      <option value="sem" <?= $filters['estoque'] === 'sem' ? 'selected' : '' ?>>Sem estoque</option>
      <option value="com" <?= $filters['estoque'] === 'com' ? 'selected' : '' ?>>Com estoque</option>
    </select>
  </label>
  <button class="btn btn-soft" type="submit">Filtrar</button>
  <a class="btn btn-outline" href="<?= site_url('admin/produtos.php') ?>">Limpar</a>
</form>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Total</span><strong><?= $stats['total'] ?></strong><small>Produtos cadastrados</small></article>
  <article class="admin-kpi-card"><span>Disponíveis</span><strong><?= $stats['disponiveis'] ?></strong><small>Venda online ativa</small></article>
  <article class="admin-kpi-card"><span>Sob encomenda</span><strong><?= $stats['encomendas'] ?></strong><small>Curadoria manual</small></article>
  <article class="admin-kpi-card"><span>Destaques</span><strong><?= $stats['destaques'] ?></strong><small>Home e catálogo</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Destaque</th><th>Ações</th></tr></thead>
    <tbody>
    <?php if (empty($produtos)): ?>
      <tr>
        <td colspan="7">
          <div class="admin-empty-row">
            <strong>Nenhum produto encontrado</strong>
            <span>Cadastre o primeiro produto ou ajuste os filtros.</span>
          </div>
        </td>
      </tr>
    <?php endif; ?>
    <?php foreach ($produtos as $p): ?>
      <?php
        $image = product_public_image_url($p['imagem'] ?? '');
        $status = (string) ($p['status'] ?? 'disponivel');
      ?>
      <tr>
        <td>
          <div class="admin-avatar-line">
            <span class="admin-avatar image-avatar">
              <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"><?php else: ?>A&F<?php endif; ?>
            </span>
            <div class="admin-item-title"><strong><?= e($p['nome']) ?></strong><small><?= e($p['sku'] ?? $p['slug']) ?></small></div>
          </div>
        </td>
        <td><?= e($p['categoria_nome'] ?? 'Sem categoria') ?></td>
        <td><?= (float) ($p['preco_promocional'] ?? 0) > 0 ? money_br((float) $p['preco_promocional']) : money_br((float) $p['preco']) ?></td>
        <td><?= (int) ($p['estoque'] ?? 0) ?></td>
        <td><span class="<?= $status === 'disponivel' ? 'admin-badge-ok' : ($status === 'inativo' ? 'admin-badge-danger' : 'admin-badge-warn') ?>"><?= e(status_label($status)) ?></span></td>
        <td><span class="<?= !empty($p['destaque']) ? 'admin-badge-soft' : 'admin-badge-info' ?>"><?= !empty($p['destaque']) ? 'Sim' : 'Normal' ?></span></td>
        <td>
          <div class="admin-table-actions">
            <button type="button" data-product-modal-open="<?= (int) $p['id'] ?>">Ver</button>
            <a href="<?= site_url('admin/produto-form.php?id=' . (int) $p['id']) ?>">Editar</a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script type="application/json" id="productListPayload"><?= json_encode($modalProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="admin-modal-backdrop" data-product-modal hidden>
  <section class="admin-product-modal" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
    <button class="admin-modal-close" type="button" data-product-modal-close aria-label="Fechar modal">&times;</button>
    <div class="admin-product-modal-media" data-product-modal-media>
      <div class="admin-upload-placeholder">A&F</div>
    </div>
    <div class="admin-product-modal-content">
      <span class="badge" data-product-modal-category>Categoria</span>
      <h2 id="productModalTitle" data-product-modal-title>Produto</h2>
      <p data-product-modal-description>Detalhes do produto selecionado.</p>
      <div class="admin-product-modal-price">
        <strong data-product-modal-price>R$ 0,00</strong>
        <span data-product-modal-promo hidden></span>
      </div>
      <dl class="admin-product-modal-meta">
        <div><dt>SKU</dt><dd data-product-modal-sku>-</dd></div>
        <div><dt>Status</dt><dd data-product-modal-status>-</dd></div>
        <div><dt>Estoque</dt><dd data-product-modal-stock>-</dd></div>
        <div><dt>Destaque</dt><dd data-product-modal-highlight>-</dd></div>
      </dl>
      <div class="admin-product-modal-thumbs" data-product-modal-thumbs></div>
      <div class="admin-action-row">
        <a class="btn btn-primary" data-product-modal-edit href="<?= site_url('admin/produto-form.php') ?>">Editar produto</a>
        <button class="btn btn-soft" type="button" data-product-modal-close>Fechar</button>
      </div>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
