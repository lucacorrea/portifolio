<?php
$produto = $produto ?? [];
$categoryName = (string) ($produto['categoria_nome'] ?? $produto['categoria'] ?? 'Produto');
$slug = (string) ($produto['slug'] ?? '');
$detailUrl = site_url('produto.php?slug=' . rawurlencode($slug));
$image = product_public_image_url((string) ($produto['imagem'] ?? first_image($produto)));
$price = effective_price($produto);
$originalPrice = (float) ($produto['preco'] ?? 0);
$hasPromo = (float) ($produto['preco_promocional'] ?? 0) > 0;
$status = (string) ($produto['status'] ?? 'disponivel');
$stock = max(0, (int) ($produto['estoque'] ?? 0));
$minStock = max(0, (int) ($produto['estoque_minimo'] ?? 0));
$tags = array_values(array_filter((array) ($produto['tags'] ?? [])));
$isAvailable = $status === 'disponivel' && $stock > 0 && !empty($produto['permitir_venda_online']);
$isOrderOnly = $status === 'sob_encomenda' || !empty($produto['sob_encomenda']);
$stockLabel = $stock <= 0 ? 'Sem estoque' : ($minStock > 0 && $stock <= $minStock ? 'Estoque baixo' : 'Em estoque');
?>
<article class="card product-card">
  <a class="product-img" href="<?= e($detailUrl) ?>" aria-label="Ver detalhes de <?= e($produto['nome'] ?? 'produto') ?>">
    <?php if ($image !== ''): ?>
      <img src="<?= e($image) ?>" alt="<?= e($produto['nome'] ?? 'Produto Arte&Flor') ?>" loading="lazy">
    <?php else: ?>
      <span class="product-fallback">Arte&Flor</span>
    <?php endif; ?>
    <span class="floating-tag"><?= e($categoryName) ?></span>
  </a>

  <div class="product-body">
    <div class="product-badges">
      <span class="status <?= $status === 'disponivel' ? 'status-ok' : 'status-warn' ?>"><?= e(status_label($status)) ?></span>
      <span class="badge badge-soft"><?= e($stockLabel) ?></span>
      <?php if (!empty($produto['destaque'])): ?><span class="badge badge-rose">Destaque</span><?php endif; ?>
      <?php if ($isOrderOnly): ?><span class="badge badge-soft">Sob encomenda</span><?php endif; ?>
      <?php if ($hasPromo): ?><span class="badge badge-sale">Promoção</span><?php endif; ?>
    </div>

    <h3><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h3>
    <p class="muted"><?= e($produto['descricao_curta'] ?? '') ?></p>

    <?php if (!empty($tags)): ?>
      <div class="tag-row product-card-tags">
        <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
          <span><?= e((string) $tag) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="price-stack">
      <?php if ($hasPromo && $originalPrice > 0): ?>
        <del><?= money_br($originalPrice) ?></del>
      <?php endif; ?>
      <strong class="price"><?= $price > 0 ? money_br($price) : 'Sob consulta' ?></strong>
    </div>

    <div class="product-actions">
      <a class="btn btn-soft" href="<?= e($detailUrl) ?>">Ver detalhes</a>
      <?php if ($isAvailable): ?>
        <button
          class="btn btn-primary"
          type="button"
          data-add-cart
          data-id="<?= e((string) ($produto['id'] ?? '')) ?>"
          data-nome="<?= e($produto['nome'] ?? 'Produto Arte&Flor') ?>"
          data-slug="<?= e($slug) ?>"
          data-categoria="<?= e($categoryName) ?>"
          data-preco="<?= e((string) $price) ?>"
          data-imagem="<?= e($image) ?>"
          data-estoque="<?= $stock ?>"
          data-status="<?= e($status) ?>"
        >Adicionar</button>
      <?php elseif ($isOrderOnly): ?>
        <a class="btn btn-primary" href="<?= e($detailUrl) ?>">Solicitar encomenda</a>
      <?php else: ?>
        <button class="btn btn-outline" type="button" disabled>Indisponível</button>
      <?php endif; ?>
    </div>
  </div>
</article>
