<?php
$pageTitle = 'Início';
$activePage = 'inicio';
require_once __DIR__ . '/includes/header.php';

$produtos = array_values(array_filter(load_json('produtos.json'), function ($item) {
    return !empty($item['destaque']);
}));
?>
<section class="hero" style="--hero-image: url('https://images.unsplash.com/photo-1487530811176-3780de880c2d?auto=format&fit=crop&w=1800&q=82');">
  <div class="container">
    <div class="hero-content">
      <span class="badge badge-rose">Floricultura premium demonstrativa</span>
      <h1>Arte&Flor</h1>
      <p>Buquês, arranjos, vasos e presentes florais com estética delicada, atendimento próximo e experiência pensada para encantar em cada detalhe.</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Ver catálogo</a>
        <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, quero fazer uma encomenda na Arte&Flor.') ?>">Fazer encomenda</a>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-heading">
      <span class="eyebrow">Categorias</span>
      <h2 class="section-title">Escolha por ocasião ou estilo</h2>
      <p class="section-subtitle">Uma vitrine visual para apresentar os principais caminhos de compra antes da implantação do backend.</p>
    </div>
    <div class="grid-4">
      <a class="category-card" href="<?= site_url('catalogo.php') ?>" style="--category-image: url('https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=800&q=80');">
        <h3>Buquês</h3>
        <p>Composições românticas, delicadas e personalizadas.</p>
      </a>
      <a class="category-card" href="<?= site_url('catalogo.php') ?>" style="--category-image: url('https://images.unsplash.com/photo-1487530811176-3780de880c2d?auto=format&fit=crop&w=800&q=80');">
        <h3>Arranjos</h3>
        <p>Opções elegantes para homenagens e recepções.</p>
      </a>
      <a class="category-card" href="<?= site_url('catalogo.php') ?>" style="--category-image: url('https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?auto=format&fit=crop&w=800&q=80');">
        <h3>Vasos</h3>
        <p>Plantas e flores duradouras para decorar.</p>
      </a>
      <a class="category-card" href="<?= site_url('catalogo.php') ?>" style="--category-image: url('https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=800&q=80');">
        <h3>Presentes</h3>
        <p>Cestas, cartões e mimos para surpreender.</p>
      </a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-heading">
      <span class="eyebrow">Destaques</span>
      <h2 class="section-title">Produtos para apresentação</h2>
      <p class="section-subtitle">Produtos fictícios com imagens ilustrativas, preços simulados e ações conectadas ao carrinho local.</p>
    </div>
    <div class="grid-3">
      <?php foreach (array_slice($produtos, 0, 6) as $produto): ?>
        <?php require __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <div class="actions">
      <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Abrir catálogo completo</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-heading">
      <span class="eyebrow">Como funciona</span>
      <h2 class="section-title">Compra simples, atendimento próximo</h2>
    </div>
    <div class="feature-grid">
      <article class="card step-card">
        <span class="step-number">1</span>
        <h3>Escolha o produto</h3>
        <p class="muted">Navegue pelo catálogo, veja detalhes e adicione itens ao carrinho demonstrativo.</p>
      </article>
      <article class="card step-card">
        <span class="step-number">2</span>
        <h3>Personalize</h3>
        <p class="muted">Inclua observações, mensagem para cartão, data e preferência de entrega.</p>
      </article>
      <article class="card step-card">
        <span class="step-number">3</span>
        <h3>Envie pelo WhatsApp</h3>
        <p class="muted">O checkout gera uma mensagem organizada para atendimento manual.</p>
      </article>
      <article class="card step-card">
        <span class="step-number">4</span>
        <h3>Acompanhe</h3>
        <p class="muted">A área do cliente simula status e histórico de pedidos para demonstração.</p>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container grid-2">
    <div>
      <span class="eyebrow">Datas especiais</span>
      <h2 class="section-title">Momentos que pedem flores</h2>
      <p class="section-subtitle">A vitrine já prevê campanhas sazonais, encomendas e produtos sob demanda para datas de alta procura.</p>
    </div>
    <div class="date-strip">
      <div class="date-item"><strong>Aniversário</strong><span>Buquês leves, arranjos coloridos e cartões personalizados.</span></div>
      <div class="date-item"><strong>Romance</strong><span>Rosas, kits especiais e entrega com mensagem.</span></div>
      <div class="date-item"><strong>Homenagens</strong><span>Arranjos premium para agradecimentos, visitas e recepções.</span></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-heading">
      <span class="eyebrow">Depoimentos</span>
      <h2 class="section-title">Experiência esperada da marca</h2>
    </div>
    <div class="grid-3">
      <article class="card testimonial">
        <blockquote>"Visual delicado, atendimento claro e opções fáceis de escolher."</blockquote>
        <strong>Cliente demonstrativa</strong>
      </article>
      <article class="card testimonial">
        <blockquote>"O pedido pelo WhatsApp chega organizado e reduz erro na encomenda."</blockquote>
        <strong>Cliente demonstrativa</strong>
      </article>
      <article class="card testimonial">
        <blockquote>"As páginas passam confiança para apresentar os produtos da loja."</blockquote>
        <strong>Cliente demonstrativa</strong>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="cta-band">
      <span class="badge badge-rose">Pronto para demonstração</span>
      <h2 class="section-title">Apresente a experiência Arte&Flor</h2>
      <p>Use o catálogo, simule o carrinho e finalize um pedido pelo WhatsApp sem backend real.</p>
      <div class="actions">
        <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Explorar catálogo</a>
        <a class="btn btn-outline" href="<?= site_url('checkout.php') ?>">Ir para checkout</a>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
