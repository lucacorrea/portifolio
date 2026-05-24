<?php
$activePage = 'catalogo';
require_once __DIR__ . '/includes/products.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$produto = $slug !== '' ? product_find_by_slug($slug) : product_public_find_by_id($id);

if (!$produto) {
    http_response_code(404);
    $pageTitle = 'Produto não encontrado';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="page-header compact-header">
      <div class="container">
        <span class="badge">Produto indisponível</span>
        <h1 class="section-title">Produto não encontrado</h1>
        <p class="section-subtitle">O produto pode ter sido inativado, removido do catálogo ou o link pode estar incorreto.</p>
        <div class="actions">
          <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Voltar ao catálogo</a>
        </div>
      </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = (string) ($produto['nome'] ?? 'Detalhes do produto');
$images = product_images_public((int) $produto['id']);
if (empty($images) && !empty($produto['imagem'])) {
    $images[] = [
        'url' => product_public_image_url((string) $produto['imagem']),
        'texto_alternativo' => (string) ($produto['nome'] ?? 'Produto Arte&Flor'),
        'principal' => 1,
    ];
}
$mainImage = (string) ($images[0]['url'] ?? '');
$price = effective_price($produto);
$originalPrice = (float) ($produto['preco'] ?? 0);
$hasPromo = (float) ($produto['preco_promocional'] ?? 0) > 0;
$status = (string) ($produto['status'] ?? 'disponivel');
$stock = max(0, (int) ($produto['estoque'] ?? 0));
$minStock = max(0, (int) ($produto['estoque_minimo'] ?? 0));
$categoryName = (string) ($produto['categoria_nome'] ?? 'Produto');
$tags = array_values(array_filter((array) ($produto['tags'] ?? [])));
$isAvailable = $status === 'disponivel' && $stock > 0 && !empty($produto['permitir_venda_online']);
$isOrderOnly = $status === 'sob_encomenda' || !empty($produto['sob_encomenda']);
$inventoryStatus = product_inventory_status($produto);
$relacionados = product_related((int) $produto['id'], isset($produto['categoria_id']) ? (int) $produto['categoria_id'] : null, 4);

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header compact-header">
  <div class="container">
    <span class="badge">Detalhes do produto</span>
    <h1 class="section-title"><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h1>
    <p class="section-subtitle"><?= e($produto['descricao_curta'] ?? 'Confira imagens, disponibilidade e detalhes antes de adicionar ao carrinho.') ?></p>
  </div>
</section>

<section class="section">
  <div class="container product-detail">
    <div class="product-gallery">
      <figure class="gallery-main">
        <?php if ($mainImage !== ''): ?>
          <img data-gallery-main src="<?= e($mainImage) ?>" alt="<?= e($produto['nome'] ?? 'Produto Arte&Flor') ?>">
        <?php else: ?>
          <span class="product-fallback">Arte&Flor</span>
        <?php endif; ?>
      </figure>
      <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs" aria-label="Miniaturas do produto">
          <?php foreach ($images as $index => $img): ?>
            <?php $thumbUrl = (string) ($img['url'] ?? ''); ?>
            <?php if ($thumbUrl === '') continue; ?>
            <button
              class="<?= $index === 0 ? 'active' : '' ?>"
              type="button"
              data-gallery-thumb="<?= e($thumbUrl) ?>"
              data-gallery-alt="<?= e(($produto['nome'] ?? 'Produto') . ' imagem ' . ($index + 1)) ?>"
            >
              <img src="<?= e($thumbUrl) ?>" alt="" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <article class="card product-buy-box">
      <div class="product-badges">
        <span class="badge"><?= e($categoryName) ?></span>
        <span class="status <?= $status === 'disponivel' ? 'status-ok' : 'status-warn' ?>"><?= e(status_label($status)) ?></span>
        <span class="badge badge-soft"><?= e(product_inventory_label($inventoryStatus)) ?></span>
        <?php if ($isOrderOnly): ?><span class="badge badge-soft">Sob encomenda</span><?php endif; ?>
        <?php if (!empty($produto['destaque'])): ?><span class="badge badge-rose">Destaque</span><?php endif; ?>
      </div>

      <h2><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h2>
      <?php if (!empty($produto['descricao_completa'])): ?>
        <p><?= nl2br(e((string) $produto['descricao_completa'])) ?></p>
      <?php else: ?>
        <p><?= e($produto['descricao_curta'] ?? 'Produto disponível no catálogo Arte&Flor.') ?></p>
      <?php endif; ?>

      <div class="price-stack large">
        <?php if ($hasPromo && $originalPrice > 0): ?><del><?= money_br($originalPrice) ?></del><?php endif; ?>
        <strong class="price"><?= $price > 0 ? money_br($price) : 'Sob consulta' ?></strong>
      </div>

      <div class="product-meta-grid">
        <div><span>Estoque</span><strong><?= $stock ?> un.</strong></div>
        <div><span>Mínimo</span><strong><?= $minStock ?> un.</strong></div>
        <div><span>SKU</span><strong><?= e($produto['sku'] ?? 'AF') ?></strong></div>
      </div>

      <?php if (!empty($tags)): ?>
        <div class="tag-row">
          <?php foreach ($tags as $tag): ?>
            <span><?= e((string) $tag) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="form-grid product-options">
        <label class="form-group">
          <span>Quantidade</span>
          <input id="productQty" type="number" value="1" min="1" max="<?= max(1, $stock) ?>" <?= $isAvailable ? '' : 'disabled' ?>>
        </label>
        <label class="form-group">
          <span>Mensagem para cartão</span>
          <input id="productMessage" type="text" placeholder="Ex: Com carinho, feliz aniversário" <?= $isAvailable ? '' : 'disabled' ?>>
        </label>
        <label class="form-group full">
          <span>Observações</span>
          <textarea id="productNotes" placeholder="Preferência de cor, entrega, embalagem ou instruções especiais" <?= $isAvailable ? '' : 'disabled' ?>></textarea>
        </label>
      </div>

      <div class="actions">
        <?php if ($isAvailable): ?>
          <button
            class="btn btn-primary"
            type="button"
            data-add-cart
            data-id="<?= e((string) ($produto['id'] ?? '')) ?>"
            data-nome="<?= e($produto['nome'] ?? 'Produto Arte&Flor') ?>"
            data-slug="<?= e($produto['slug'] ?? '') ?>"
            data-categoria="<?= e($categoryName) ?>"
            data-preco="<?= e((string) $price) ?>"
            data-imagem="<?= e($mainImage) ?>"
            data-estoque="<?= $stock ?>"
            data-status="<?= e($status) ?>"
            data-qty-target="#productQty"
            data-message-target="#productMessage"
            data-note-target="#productNotes"
          >Adicionar ao carrinho</button>
        <?php elseif ($isOrderOnly): ?>
          <button class="btn btn-primary" type="button" disabled>Disponível sob encomenda</button>
        <?php else: ?>
          <button class="btn btn-primary" type="button" disabled>Produto sem estoque</button>
        <?php endif; ?>
        <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento sobre o produto ' . ($produto['nome'] ?? 'Arte&Flor') . '.') ?>">Tirar dúvida</a>
      </div>
    </article>
  </div>
</section>

<?php if (!empty($relacionados)): ?>
  <section class="section section-soft">
    <div class="container">
      <div class="section-heading">
        <div>
          <span class="badge">Relacionados</span>
          <h2 class="section-title">Você também pode gostar</h2>
        </div>
        <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Voltar ao catálogo</a>
      </div>
      <div class="grid-3 product-grid-spaced">
        <?php foreach ($relacionados as $produtoRelacionado): ?>
          <?php $produto = $produtoRelacionado; ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
