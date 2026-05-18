<?php
$pageTitle = 'Início';
$activePage = 'inicio';
require_once __DIR__ . '/includes/header.php';
$produtos = load_json('produtos.json');
$destaques = array_values(array_filter($produtos, fn($item) => !empty($item['destaque'])));
$categorias = [
    ['nome' => 'Buquês', 'texto' => 'Rosas, tons pastel e composições românticas.', 'imagem' => 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=700&q=80'],
    ['nome' => 'Arranjos', 'texto' => 'Peças elegantes para homenagens e eventos.', 'imagem' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=700&q=80'],
    ['nome' => 'Vasos', 'texto' => 'Orquídeas, violetas e plantas para decorar.', 'imagem' => 'https://images.unsplash.com/photo-1485955900006-10f4d324d411?auto=format&fit=crop&w=700&q=80'],
    ['nome' => 'Presentes', 'texto' => 'Cestas, kits e mimos para datas especiais.', 'imagem' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=700&q=80'],
];
?>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="badge">Floricultura premium em Coari-AM</span>
      <h1>Flores com presença para momentos que merecem cuidado.</h1>
      <p>Escolha no catálogo, personalize o cartão, finalize o pedido no sistema e acompanhe o status pela área do cliente.</p>
      <div class="actions">
        <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Ver catálogo</a>
        <a class="btn btn-outline" href="<?= site_url('cliente.php') ?>">Acompanhar pedido</a>
      </div>
      <div class="hero-trust">
        <span>Pedido no sistema</span>
        <span>Pix demonstrativo</span>
        <span>Entrega ou retirada</span>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true"></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-heading">
      <div>
        <span class="badge">Categorias</span>
        <h2 class="section-title">Escolha por ocasião</h2>
      </div>
      <p class="section-subtitle">Um catálogo visual para compra rápida, com produtos prontos e opções sob encomenda.</p>
    </div>
    <div class="category-grid">
      <?php foreach ($categorias as $categoria): ?>
        <a class="category-card" href="<?= site_url('catalogo.php') ?>">
          <img src="<?= e($categoria['imagem']) ?>" alt="<?= e($categoria['nome']) ?>" loading="lazy">
          <span><?= e($categoria['nome']) ?></span>
          <p><?= e($categoria['texto']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-soft">
  <div class="container">
    <div class="section-heading">
      <div>
        <span class="badge">Destaques</span>
        <h2 class="section-title">Produtos prontos para encantar</h2>
      </div>
      <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Catálogo completo</a>
    </div>
    <div class="grid-3 product-grid-spaced">
      <?php foreach (array_slice($destaques, 0, 6) as $produto): ?>
        <?php require __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container split-feature">
    <div>
      <span class="badge">Como funciona</span>
      <h2 class="section-title">Compra visual do início ao fim</h2>
      <p class="section-subtitle">O MVP não usa backend real, mas já demonstra o fluxo comercial correto para a apresentação.</p>
    </div>
    <div class="steps-grid">
      <article class="card step-card"><strong>1</strong><h3>Escolha</h3><p>Cliente navega pelo catálogo, vê detalhes e adiciona ao carrinho.</p></article>
      <article class="card step-card"><strong>2</strong><h3>Finalize</h3><p>Checkout coleta entrega, retirada, mensagem e pagamento demonstrativo.</p></article>
      <article class="card step-card"><strong>3</strong><h3>Acompanhe</h3><p>Pedido fictício fica salvo no navegador e aparece na área do cliente.</p></article>
    </div>
  </div>
</section>

<section class="section section-band">
  <div class="container confidence-grid">
    <article>
      <span class="badge badge-rose">Diferenciais</span>
      <h2>Curadoria floral, operação simples e apresentação premium.</h2>
    </article>
    <div class="feature-list-card">
      <span>Produtos com imagens reais e descrições claras</span>
      <span>Entrega ou retirada com horário desejado</span>
      <span>Checkout com Pix visual demonstrativo</span>
      <span>Área administrativa com PDV e histórico</span>
    </div>
  </div>
</section>

<section class="section">
  <div class="container delivery-grid">
    <article class="card feature-card">
      <span class="badge">Entrega</span>
      <h3>Receba no endereço desejado</h3>
      <p>Bairro, referência, data e horário ficam registrados no pedido demonstrativo.</p>
    </article>
    <article class="card feature-card">
      <span class="badge">Retirada</span>
      <h3>Pedido preparado para buscar</h3>
      <p>Ideal para compras rápidas, encomendas e pagamentos na retirada.</p>
    </article>
    <article class="card feature-card">
      <span class="badge">Atendimento</span>
      <h3>Suporte sem sair do fluxo</h3>
      <p>WhatsApp aparece como canal secundário para dúvidas, não como finalização da compra.</p>
      <a class="btn btn-soft" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento da Arte&Flor.') ?>">Falar com atendimento</a>
    </article>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
