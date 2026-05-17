<?php
$produto = $produto ?? [];
$preco = product_price($produto);
$imagem = $produto['imagem'] ?? ($produto['galeria'][0] ?? '');
$alt = $produto['alt'] ?? ('Imagem ilustrativa de ' . ($produto['nome'] ?? 'produto floral'));
$status = $produto['status'] ?? 'Disponível';
$cartPayload = json_encode([
    'id' => (int) ($produto['id'] ?? 0),
    'nome' => $produto['nome'] ?? 'Produto Arte&Flor',
    'preco' => $preco,
    'imagem' => $imagem,
    'slug' => $produto['slug'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$mensagem = 'Olá, tenho interesse no produto: ' . ($produto['nome'] ?? 'Produto Arte&Flor');
?>
<article class="product-card">
  <a class="product-media" href="<?= site_url('produto.php?slug=' . rawurlencode((string) ($produto['slug'] ?? ''))) ?>" aria-label="Ver detalhes de <?= e($produto['nome'] ?? 'produto') ?>">
    <img src="<?= e($imagem) ?>" alt="<?= e($alt) ?>" loading="lazy">
    <span class="status-badge"><?= e($status) ?></span>
  </a>
  <div class="product-body">
    <span class="eyebrow"><?= e($produto['categoria'] ?? 'Produto') ?></span>
    <h3><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h3>
    <p><?= e($produto['descricao_curta'] ?? '') ?></p>
    <div class="product-price-row">
      <strong class="price"><?= $preco > 0 ? money_br($preco) : 'Consultar valor' ?></strong>
      <?php if (!empty($produto['preco_promocional']) && (float) $produto['preco_promocional'] > 0): ?>
        <span class="old-price"><?= money_br((float) $produto['preco']) ?></span>
      <?php endif; ?>
    </div>
    <div class="product-actions">
      <a class="btn btn-soft" href="<?= site_url('produto.php?slug=' . rawurlencode((string) ($produto['slug'] ?? ''))) ?>">Ver detalhes</a>
      <button class="btn btn-primary" type="button" data-cart-add data-product="<?= e($cartPayload) ?>">Adicionar</button>
      <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= whatsapp_url($mensagem) ?>">WhatsApp</a>
    </div>
  </div>
</article>
