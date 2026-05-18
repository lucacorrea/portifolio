<?php
$pageTitle = 'Detalhes do produto';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/header.php';
$slug = $_GET['slug'] ?? '';
$produtos = load_json('produtos.json');
$produto = current(array_filter($produtos, fn($p) => ($p['slug'] ?? '') === $slug)) ?: ($produtos[0] ?? []);
$images = array_values(array_filter($produto['imagens'] ?? []));
$mainImage = $images[0] ?? '';
$price = effective_price($produto);
$originalPrice = (float) ($produto['preco'] ?? 0);
$hasPromo = (float) ($produto['preco_promocional'] ?? 0) > 0;
$status = (string) ($produto['status'] ?? 'disponivel');
$relacionados = array_values(array_filter($produtos, fn($p) => ($p['categoria'] ?? '') === ($produto['categoria'] ?? '') && ($p['id'] ?? null) !== ($produto['id'] ?? null)));
?>
<section class="page-header compact-header">
  <div class="container">
    <span class="badge">Detalhes do produto</span>
    <h1 class="section-title"><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h1>
    <p class="section-subtitle">Confira imagens, disponibilidade e personalize a mensagem antes de adicionar ao carrinho.</p>
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
            <button
              class="<?= $index === 0 ? 'active' : '' ?>"
              type="button"
              data-gallery-thumb="<?= e($img) ?>"
              data-gallery-alt="<?= e(($produto['nome'] ?? 'Produto') . ' imagem ' . ($index + 1)) ?>"
            >
              <img src="<?= e($img) ?>" alt="" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <article class="card product-buy-box">
      <div class="product-badges">
        <span class="badge"><?= e($produto['categoria'] ?? 'Produto') ?></span>
        <span class="status <?= $status === 'disponivel' ? 'status-ok' : 'status-warn' ?>"><?= e(status_label($status)) ?></span>
        <?php if (!empty($produto['sob_encomenda'])): ?><span class="badge badge-soft">Sob encomenda</span><?php endif; ?>
        <?php if (!empty($produto['destaque'])): ?><span class="badge badge-rose">Destaque</span><?php endif; ?>
      </div>

      <h2><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h2>
      <p><?= e($produto['descricao_completa'] ?? '') ?></p>

      <div class="price-stack large">
        <?php if ($hasPromo && $originalPrice > 0): ?><del><?= money_br($originalPrice) ?></del><?php endif; ?>
        <strong class="price"><?= $price > 0 ? money_br($price) : 'Sob consulta' ?></strong>
      </div>

      <div class="product-meta-grid">
        <div><span>Estoque</span><strong><?= (int) ($produto['estoque'] ?? 0) ?> un.</strong></div>
        <div><span>Status</span><strong><?= e(status_label($status)) ?></strong></div>
        <div><span>SKU</span><strong><?= e($produto['sku'] ?? 'AF-DEMO') ?></strong></div>
      </div>

      <div class="tag-row">
        <?php foreach (($produto['tags'] ?? []) as $tag): ?>
          <span><?= e($tag) ?></span>
        <?php endforeach; ?>
      </div>

      <div class="form-grid product-options">
        <label class="form-group">
          <span>Quantidade</span>
          <input id="productQty" type="number" value="1" min="1" max="20">
        </label>
        <label class="form-group">
          <span>Mensagem para cartão</span>
          <input id="productMessage" type="text" placeholder="Ex: Com carinho, feliz aniversário">
        </label>
        <label class="form-group full">
          <span>Observações</span>
          <textarea id="productNotes" placeholder="Preferência de cor, entrega, embalagem ou instruções especiais"></textarea>
        </label>
      </div>

      <div class="actions">
        <button
          class="btn btn-primary"
          type="button"
          data-add-cart
          data-id="<?= e((string) ($produto['id'] ?? '')) ?>"
          data-nome="<?= e($produto['nome'] ?? 'Produto Arte&Flor') ?>"
          data-slug="<?= e($produto['slug'] ?? '') ?>"
          data-categoria="<?= e($produto['categoria'] ?? '') ?>"
          data-preco="<?= e((string) $price) ?>"
          data-imagem="<?= e($mainImage) ?>"
          data-qty-target="#productQty"
          data-message-target="#productMessage"
          data-note-target="#productNotes"
        >Adicionar ao carrinho</button>
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
        <?php foreach (array_slice($relacionados, 0, 3) as $produto): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
