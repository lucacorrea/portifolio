<?php
$pageTitle = 'Início';
$activePage = 'inicio';
require_once __DIR__ . '/includes/header.php';
$produtos = array_filter(load_json('produtos.json'), fn($item) => !empty($item['destaque']));
?>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="badge">Entrega em Coari-AM</span>
      <h1>Flores que <em>transformam</em> momentos em memórias</h1>
      <p>Buquês, arranjos, vasos e presentes especiais preparados com carinho para encantar quem você ama.</p>
      <div class="actions">
        <a class="btn btn-primary" href="catalogo.php">Ver catálogo</a>
        <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, quero fazer uma encomenda na Arte&Flor.') ?>">Fazer encomenda</a>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true">💐</div>
  </div>
</section>
<section class="section">
  <div class="container">
    <h2 class="section-title">Categorias principais</h2>
    <p class="section-subtitle">Organize a compra por ocasião, tipo de presente e disponibilidade.</p>
    <div class="grid-4" style="margin-top:24px">
      <div class="card"><h3>Buquês</h3><p class="muted">Rosas, tons pastel e buquês personalizados.</p></div>
      <div class="card"><h3>Arranjos</h3><p class="muted">Composições florais para datas especiais.</p></div>
      <div class="card"><h3>Vasos</h3><p class="muted">Plantas, violetas e opções para decoração.</p></div>
      <div class="card"><h3>Presentes</h3><p class="muted">Cestas, cartões e kits personalizados.</p></div>
    </div>
  </div>
</section>
<section class="section">
  <div class="container">
    <h2 class="section-title">Produtos em destaque</h2>
    <div class="grid-3" style="margin-top:24px">
      <?php foreach (array_slice($produtos, 0, 6) as $produto): ?>
        <?php require __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<section class="section">
  <div class="container card">
    <h2 class="section-title">Quer surpreender alguém hoje?</h2>
    <p class="section-subtitle">Escolha um produto no catálogo ou envie sua ideia para montarmos uma encomenda personalizada.</p>
    <div class="actions">
      <a class="btn btn-primary" href="catalogo.php">Comprar pelo catálogo</a>
      <a class="btn btn-soft" href="checkout.php">Ir para checkout</a>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
