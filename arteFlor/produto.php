<?php
$pageTitle = 'Detalhes do produto';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/header.php';

$slug = $_GET['slug'] ?? '';
$produtos = load_json('produtos.json');
$produto = current(array_filter($produtos, function ($p) use ($slug) {
    return ($p['slug'] ?? '') === $slug;
})) ?: ($produtos[0] ?? []);
$preco = product_price($produto);
$galeria = $produto['galeria'] ?? [$produto['imagem'] ?? ''];
$relacionados = array_values(array_filter($produtos, function ($p) use ($produto) {
    return ($p['categoria'] ?? '') === ($produto['categoria'] ?? '') && ($p['id'] ?? null) !== ($produto['id'] ?? null);
}));
$cartPayload = json_encode([
    'id' => (int) ($produto['id'] ?? 0),
    'nome' => $produto['nome'] ?? 'Produto Arte&Flor',
    'preco' => $preco,
    'imagem' => $produto['imagem'] ?? '',
    'slug' => $produto['slug'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<section class="page-hero">
  <div class="container">
    <span class="badge"><?= e($produto['categoria'] ?? 'Produto') ?></span>
    <h1 class="section-title"><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h1>
    <p class="section-subtitle"><?= e($produto['descricao_curta'] ?? 'Produto demonstrativo da Arte&Flor.') ?></p>
  </div>
</section>

<section class="section">
  <div class="container product-detail">
    <div>
      <div class="gallery-main">
        <img src="<?= e($galeria[0] ?? '') ?>" alt="<?= e($produto['alt'] ?? 'Imagem do produto Arte&Flor') ?>" data-gallery-main loading="lazy">
      </div>
      <div class="gallery-thumbs" aria-label="Galeria do produto">
        <?php foreach ($galeria as $index => $imagem): ?>
          <button class="gallery-thumb <?= $index === 0 ? 'active' : '' ?>" type="button" data-gallery-thumb="<?= e($imagem) ?>" aria-label="Ver imagem <?= $index + 1 ?>">
            <img src="<?= e($imagem) ?>" alt="<?= e(($produto['alt'] ?? 'Produto Arte&Flor') . ' - imagem ' . ($index + 1)) ?>" loading="lazy">
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="product-info" data-product-purchase>
      <article class="card product-info-card">
        <span class="eyebrow"><?= e($produto['status'] ?? 'Disponível') ?></span>
        <h2><?= e($produto['nome'] ?? 'Produto Arte&Flor') ?></h2>
        <p class="muted"><?= e($produto['descricao_completa'] ?? '') ?></p>
        <strong class="price"><?= $preco > 0 ? money_br($preco) : 'Consultar valor' ?></strong>

        <ul class="product-info-list">
          <li><span>Categoria</span><strong><?= e($produto['categoria'] ?? '-') ?></strong></li>
          <li><span>Estoque demonstrativo</span><strong><?= (int) ($produto['estoque'] ?? 0) ?> un.</strong></li>
          <li><span>Entrega</span><strong>Combinar no WhatsApp</strong></li>
        </ul>

        <div class="form-grid">
          <label class="form-group">
            <span>Quantidade</span>
            <input type="number" value="1" min="1" data-product-quantity>
          </label>
          <label class="form-group">
            <span>Mensagem para cartão</span>
            <input type="text" name="cartao" placeholder="Ex: Feliz aniversário">
          </label>
          <label class="form-group full">
            <span>Observações</span>
            <textarea name="observacoes" placeholder="Preferência de cor, horário, embalagem ou entrega"></textarea>
          </label>
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="button" data-cart-add data-product="<?= e($cartPayload) ?>">Adicionar ao carrinho</button>
          <a class="btn btn-soft" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, tenho interesse no produto: ' . ($produto['nome'] ?? 'Produto Arte&Flor')) ?>">Comprar pelo WhatsApp</a>
        </div>
      </article>

      <article class="panel">
        <h3>Informações de entrega</h3>
        <p class="muted">Prazos, taxa de entrega e disponibilidade das flores serão confirmados manualmente pelo WhatsApp. Este MVP não processa pagamento real.</p>
      </article>
    </div>
  </div>
</section>

<?php if (!empty($relacionados)): ?>
<section class="section">
  <div class="container">
    <div class="section-heading">
      <span class="eyebrow">Relacionados</span>
      <h2 class="section-title">Você também pode gostar</h2>
    </div>
    <div class="grid-3">
      <?php foreach (array_slice($relacionados, 0, 3) as $produto): ?>
        <?php require __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
