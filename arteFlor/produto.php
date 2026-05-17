<?php
$pageTitle = 'Detalhes do produto';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/header.php';
$slug = $_GET['slug'] ?? '';
$produtos = load_json('produtos.json');
$produto = current(array_filter($produtos, fn($p) => $p['slug'] === $slug)) ?: $produtos[0];
$preco = (float) ($produto['preco_promocional'] ?: $produto['preco'] ?: 0);
?>
<section class="section">
  <div class="container product-detail">
    <div>
      <div class="gallery-main"><?= e($produto['imagens'][0] ?? '💐') ?></div>
      <div class="filters">
        <?php foreach (($produto['imagens'] ?? []) as $img): ?><span class="filter-pill"><?= e($img) ?></span><?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <span class="badge"><?= e($produto['categoria']) ?></span>
      <h1 class="section-title"><?= e($produto['nome']) ?></h1>
      <p class="muted"><?= e($produto['descricao_completa']) ?></p>
      <p class="price"><?= $preco > 0 ? money_br($preco) : 'Consultar valor' ?></p>
      <p><strong>Status:</strong> <?= e($produto['status']) ?> · <strong>Estoque:</strong> <?= (int) $produto['estoque'] ?></p>
      <div class="form-grid" style="margin-top:18px">
        <label class="form-group"><span>Quantidade</span><input type="number" value="1" min="1"></label>
        <label class="form-group"><span>Mensagem para cartão</span><input type="text" placeholder="Ex: Feliz aniversário"></label>
        <label class="form-group full"><span>Observações</span><textarea placeholder="Preferência de cor, horário ou entrega"></textarea></label>
      </div>
      <div class="actions">
        <button class="btn btn-primary" onclick='ArteFlor.addToCart(<?= json_encode(["id"=>$produto["id"],"nome"=>$produto["nome"],"preco"=>$preco], JSON_UNESCAPED_UNICODE) ?>)'>Adicionar ao carrinho</button>
        <a class="btn btn-soft" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, tenho interesse no produto: ' . $produto['nome']) ?>">Comprar no WhatsApp</a>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
