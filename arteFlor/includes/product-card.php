<?php
$produto = $produto ?? [];
$image = first_image($produto);
$price = effective_price($produto);
$originalPrice = (float) ($produto['preco'] ?? 0);
$hasPromo = (float) ($produto['preco_promocional'] ?? 0) > 0;
$status = (string) ($produto['status'] ?? 'disponivel');
?>
<article class="card product-card">
  <a class="product-img" href="<?= site_url('produto.php?slug=' . rawurlencode((string) ($produto['slug'] ?? ''))) ?>" aria-label="Ver detalhes de <?= e($produto['nome'] ?? 'produto') ?>">
    <?php if ($image !== ''): ?>
      <img src="<?= e($image) ?>" alt="<?= e($produto['nome'] ?? 'Produto Arte&Flor') ?>" loading="lazy">
    <?php else: ?>
      <span class="product-fallback">Arte&Flor</span>
    <?php endif; ?>
    <span class="floating-tag"><?= e($produto['categoria'] ?? 'Produto') ?></span>
  </a>

  <div class="product-body">
    <div class="product-badges">
      <span class="status <?= $status === 'disponivel' ? 'status-ok' : 'status-warn' ?>"><?= e(status_label($status)) ?></span>
      <?php if (!empty($produto['destaque'])): ?><span class="badge badge-rose">Destaque</span><?php endif; ?>
      <?php if (!empty($produto['sob_encomenda'])): ?><span class="badge badge-soft">Sob encomenda</span><?php endif; ?>
      <?php if ($hasPromo): ?><span class="badge badge-sale">Promoção</span><?php endif; ?>
    </div>

    <h3><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h3>
    <p class="muted"><?= e($produto['descricao_curta'] ?? '') ?></p>

    <div class="price-stack">
      <?php if ($hasPromo && $originalPrice > 0): ?>
        <del><?= money_br($originalPrice) ?></del>
      <?php endif; ?>
      <strong class="price"><?= $price > 0 ? money_br($price) : 'Sob consulta' ?></strong>
    </div>

    <div class="product-actions">
      <a class="btn btn-soft" href="<?= site_url('produto.php?slug=' . rawurlencode((string) ($produto['slug'] ?? ''))) ?>">Detalhes</a>
      <button
        class="btn btn-primary"
        type="button"
        data-add-cart
        data-id="<?= e((string) ($produto['id'] ?? '')) ?>"
        data-nome="<?= e($produto['nome'] ?? 'Produto Arte&Flor') ?>"
        data-slug="<?= e($produto['slug'] ?? '') ?>"
        data-categoria="<?= e($produto['categoria'] ?? '') ?>"
        data-preco="<?= e((string) $price) ?>"
        data-imagem="<?= e($image) ?>"
      >Adicionar</button>
    </div>
  </div>
</article>
