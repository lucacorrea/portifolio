<?php
$produto = $produto ?? [];
$preco = (float) ($produto['preco_promocional'] ?: $produto['preco'] ?: 0);
$mensagem = 'Olá, vim pelo catálogo da Arte&Flor. Tenho interesse no produto: ' . ($produto['nome'] ?? 'Produto');
?>
<article class="card product-card">
  <div class="product-img" aria-hidden="true"><?= e($produto['imagens'][0] ?? '💐') ?></div>
  <div class="product-body">
    <span class="badge"><?= e($produto['categoria'] ?? 'Produto') ?></span>
    <h3><?= e($produto['nome'] ?? 'Produto') ?></h3>
    <p class="muted"><?= e($produto['descricao_curta'] ?? '') ?></p>
    <p class="price"><?= $preco > 0 ? money_br($preco) : 'Consultar valor' ?></p>
    <div class="actions">
      <a class="btn btn-soft" href="produto.php?slug=<?= e($produto['slug'] ?? '') ?>">Detalhes</a>
      <a class="btn btn-primary" target="_blank" rel="noopener" href="<?= whatsapp_url($mensagem) ?>">WhatsApp</a>
    </div>
  </div>
</article>
