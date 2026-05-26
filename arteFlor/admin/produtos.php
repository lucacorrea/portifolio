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
$adminMessage = product_admin_message_from_query();
$csrfToken = admin_csrf_token();
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

    $inventoryStatus = product_inventory_status($product);

    return [
        'id' => $productId,
        'nome' => (string) ($product['nome'] ?? ''),
        'sku' => (string) ($product['sku'] ?? ''),
        'slug' => (string) ($product['slug'] ?? ''),
        'categoria' => (string) ($product['categoria_nome'] ?? 'Sem categoria'),
        'statusValue' => (string) ($product['status'] ?? 'disponivel'),
        'status' => status_label((string) ($product['status'] ?? 'disponivel')),
        'preco' => money_br((float) ($product['preco'] ?? 0)),
        'precoPromocional' => (float) ($product['preco_promocional'] ?? 0) > 0 ? money_br((float) $product['preco_promocional']) : '',
        'estoque' => (int) ($product['estoque'] ?? 0),
        'estoqueMinimo' => (int) ($product['estoque_minimo'] ?? 0),
        'stockStatus' => $inventoryStatus,
        'stockLabel' => product_inventory_label($inventoryStatus),
        'stockBadgeClass' => product_inventory_badge_class($inventoryStatus),
        'stockPercent' => product_inventory_percent($product),
        'descricaoCurta' => (string) ($product['descricao_curta'] ?? ''),
        'descricaoCompleta' => (string) ($product['descricao_completa'] ?? ''),
        'tags' => array_values($product['tags'] ?? []),
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

<?php if ($adminMessage): ?>
  <div class="admin-alert-card <?= e($adminMessage['class']) ?>" role="status">
    <strong><?= e($adminMessage['title']) ?></strong>
    <?= e($adminMessage['body']) ?>
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
      <option value="sem" <?= $filters['estoque'] === 'sem' ? 'selected' : '' ?>>Sem estoque</option>
      <option value="baixo" <?= $filters['estoque'] === 'baixo' ? 'selected' : '' ?>>Baixo</option>
      <option value="medio" <?= $filters['estoque'] === 'medio' ? 'selected' : '' ?>>Médio</option>
      <option value="normal" <?= $filters['estoque'] === 'normal' ? 'selected' : '' ?>>Normal</option>
    </select>
  </label>
  <button class="btn btn-soft" type="submit">Filtrar</button>
  <a class="btn btn-outline" href="<?= site_url('admin/produtos.php') ?>">Limpar</a>
</form>

<section class="admin-kpi-grid five">
  <article class="admin-kpi-card kpi-soft"><span>Total</span><strong><?= $stats['total'] ?></strong><small>Produtos cadastrados</small></article>
  <article class="admin-kpi-card kpi-ok"><span>Disponíveis</span><strong><?= $stats['disponiveis'] ?></strong><small>Venda online ativa</small></article>
  <article class="admin-kpi-card kpi-danger"><span>Sem estoque</span><strong><?= $stats['sem_estoque'] ?></strong><small>Reposição urgente</small></article>
  <article class="admin-kpi-card kpi-warning"><span>Estoque baixo</span><strong><?= $stats['estoque_baixo'] ?></strong><small>Abaixo do mínimo</small></article>
  <article class="admin-kpi-card kpi-info"><span>Destaques</span><strong><?= $stats['destaques'] ?></strong><small>Home e catálogo</small></article>
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
        $inventoryStatus = product_inventory_status($p);
        $inventoryPercent = product_inventory_percent($p);
        $tags = array_values($p['tags'] ?? []);
        $statusAction = $status === 'inativo' ? 'ativar' : 'inativar';
      ?>
      <tr class="<?= e(product_inventory_row_class($inventoryStatus)) ?>">
        <td>
          <div class="admin-avatar-line">
            <span class="admin-avatar image-avatar">
              <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"><?php else: ?>A&F<?php endif; ?>
            </span>
            <div class="admin-item-title">
              <strong><?= e($p['nome']) ?></strong>
              <small><?= e($p['sku'] ?? $p['slug']) ?></small>
              <?php if (!empty($tags)): ?>
                <div class="admin-tag-list">
                  <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                    <span><?= e((string) $tag) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td><?= e($p['categoria_nome'] ?? 'Sem categoria') ?></td>
        <td><?= (float) ($p['preco_promocional'] ?? 0) > 0 ? money_br((float) $p['preco_promocional']) : money_br((float) $p['preco']) ?></td>
        <td>
          <div class="inventory-stock-cell">
            <div class="inventory-stock-meta">
              <strong>Estoque: <?= (int) ($p['estoque'] ?? 0) ?> un.</strong>
              <small>Mínimo: <?= (int) ($p['estoque_minimo'] ?? 0) ?> un.</small>
            </div>
            <div class="inventory-stock-bar" aria-hidden="true">
              <span class="inventory-stock-fill <?= e($inventoryStatus) ?>" style="width: <?= $inventoryPercent ?>%"></span>
            </div>
            <span class="<?= e(product_inventory_badge_class($inventoryStatus)) ?>"><?= e(product_inventory_label($inventoryStatus)) ?></span>
          </div>
        </td>
        <td><span class="<?= $status === 'disponivel' ? 'admin-badge-ok' : ($status === 'inativo' ? 'admin-badge-danger' : 'admin-badge-warn') ?>"><?= e(status_label($status)) ?></span></td>
        <td><span class="<?= !empty($p['destaque']) ? 'admin-badge-soft' : 'admin-badge-info' ?>"><?= !empty($p['destaque']) ? 'Sim' : 'Normal' ?></span></td>
        <td>
          <div class="admin-table-actions">
            <button type="button" data-product-modal-open="<?= (int) $p['id'] ?>">Ver</button>
            <a href="<?= site_url('admin/produto-form.php?id=' . (int) $p['id']) ?>">Editar</a>
            <form method="post" action="<?= site_url('admin/actions/produto-duplicar.php') ?>" data-confirm="Duplicar este produto? A cópia será criada inativa e com estoque zerado.">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
              <button type="submit">Duplicar</button>
            </form>
            <form method="post" action="<?= site_url('admin/actions/produto-status.php') ?>" data-confirm="<?= $statusAction === 'ativar' ? 'Ativar este produto?' : 'Inativar este produto e ocultar da venda pública?' ?>">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="action" value="<?= e($statusAction) ?>">
              <button type="submit" class="<?= $statusAction === 'ativar' ? 'admin-action-success' : 'admin-action-danger' ?>"><?= $statusAction === 'ativar' ? 'Ativar' : 'Inativar' ?></button>
            </form>
            <form method="post" action="<?= site_url('admin/actions/produto-excluir.php') ?>" data-product-delete-form data-product-name="<?= e((string) $p['nome']) ?>">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="admin-action-danger">Excluir</button>
            </form>
            <button
              type="button"
              data-stock-modal-open
              data-product-id="<?= (int) $p['id'] ?>"
              data-product-name="<?= e((string) $p['nome']) ?>"
              data-product-sku="<?= e((string) ($p['sku'] ?? '')) ?>"
              data-product-stock="<?= (int) ($p['estoque'] ?? 0) ?>"
              data-product-min-stock="<?= (int) ($p['estoque_minimo'] ?? 0) ?>"
            >Entrada/Saída</button>
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
        <div><dt>Slug</dt><dd data-product-modal-slug>-</dd></div>
        <div><dt>Status</dt><dd data-product-modal-status>-</dd></div>
        <div><dt>Destaque</dt><dd data-product-modal-highlight>-</dd></div>
        <div><dt>Sob encomenda</dt><dd data-product-modal-order>-</dd></div>
      </dl>
      <div class="admin-product-modal-tags admin-tag-list" data-product-modal-tags hidden></div>
      <div class="admin-product-modal-stock inventory-stock-cell">
        <div class="inventory-stock-meta">
          <strong data-product-modal-stock>Estoque: -</strong>
          <small data-product-modal-min-stock>Mínimo: -</small>
        </div>
        <div class="inventory-stock-bar" aria-hidden="true">
          <span class="inventory-stock-fill" data-product-modal-stock-fill style="width: 0%"></span>
        </div>
        <span class="admin-badge-soft" data-product-modal-stock-label>Estoque</span>
      </div>
      <div class="admin-product-modal-descriptions">
        <section>
          <h3>Descrição curta</h3>
          <p data-product-modal-short>Produto sem descrição curta cadastrada.</p>
        </section>
        <section>
          <h3>Descrição completa</h3>
          <p data-product-modal-full>Produto sem descrição completa cadastrada.</p>
        </section>
      </div>
      <div class="admin-product-modal-thumbs" data-product-modal-thumbs></div>
      <div class="admin-action-row">
        <a class="btn btn-primary" data-product-modal-edit href="<?= site_url('admin/produto-form.php') ?>">Editar produto</a>
        <button class="btn btn-soft" type="button" data-product-modal-close>Fechar</button>
      </div>
    </div>
  </section>
</div>
<div class="admin-modal-backdrop admin-stock-modal-backdrop" data-stock-modal hidden>
  <section class="admin-stock-modal" role="dialog" aria-modal="true" aria-labelledby="stockModalTitle">
    <button class="admin-modal-close" type="button" data-stock-modal-close aria-label="Fechar modal">&times;</button>
    <div class="admin-panel-header compact">
      <div>
        <span class="badge">Estoque</span>
        <h2 id="stockModalTitle">Movimentar produto</h2>
        <p>Registre entrada, saída, ajuste ou perda com saldo auditável.</p>
      </div>
    </div>
    <div class="admin-stock-summary">
      <div><span>Produto</span><strong data-stock-product-name>-</strong></div>
      <div><span>SKU</span><strong data-stock-product-sku>-</strong></div>
      <div><span>Estoque atual</span><strong data-stock-product-current>-</strong></div>
      <div><span>Estoque mínimo</span><strong data-stock-product-min>-</strong></div>
    </div>
    <form class="admin-stock-form" method="post" action="<?= site_url('admin/actions/produto-estoque.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="product_id" data-stock-product-id value="">
      <label class="admin-field">
        <span>Tipo</span>
        <select name="tipo" required>
          <option value="entrada">Entrada</option>
          <option value="saida">Saída</option>
          <option value="ajuste">Ajuste</option>
          <option value="perda">Perda</option>
        </select>
      </label>
      <label class="admin-field">
        <span>Quantidade</span>
        <input name="quantidade" type="number" min="0" step="1" value="1" required>
      </label>
      <label class="admin-field full">
        <span>Motivo</span>
        <input name="motivo" maxlength="255" placeholder="Ex: reposição, venda presencial, conferência ou avaria">
      </label>
      <div class="admin-action-row">
        <button class="btn btn-primary" type="submit">Registrar movimentação</button>
        <button class="btn btn-soft" type="button" data-stock-modal-close>Cancelar</button>
      </div>
    </form>
  </section>
</div>
<div class="admin-modal-backdrop" data-product-delete-modal hidden>
  <section class="admin-stock-modal admin-delete-modal" role="dialog" aria-modal="true" aria-labelledby="deleteProductModalTitle">
    <button class="admin-modal-close" type="button" data-product-delete-cancel aria-label="Cancelar exclusão">&times;</button>
    <div class="admin-panel-header compact">
      <div>
        <span class="badge">Confirmação</span>
        <h2 id="deleteProductModalTitle">Excluir produto?</h2>
        <p>Essa ação remove o produto do catálogo, do PDV e da listagem administrativa, preservando pedidos e movimentações anteriores.</p>
      </div>
    </div>
    <div class="admin-alert-card admin-alert-warning product-delete-warning" role="alert">
      <strong data-product-delete-name>Produto selecionado</strong>
      Confirme apenas se este produto não deve mais aparecer para venda.
    </div>
    <div class="admin-action-row">
      <button class="btn btn-soft" type="button" data-product-delete-cancel>Cancelar</button>
      <button class="btn btn-danger" type="button" data-product-delete-confirm>Excluir produto</button>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
