<?php
$pageTitle = 'Início';
$activePage = 'inicio';
require_once __DIR__ . '/includes/products.php';
$destaques = product_featured(6);
if (empty($destaques)) {
    $destaques = product_public_list(['limit' => 6]);
}
$categorias = product_public_categories();
$fallbackCategoryImages = [
    'buques' => 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=700&q=80',
    'arranjos' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=700&q=80',
    'vasos' => 'https://images.unsplash.com/photo-1485955900006-10f4d324d411?auto=format&fit=crop&w=700&q=80',
    'presentes' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=700&q=80',
    'plantas' => 'https://images.unsplash.com/photo-1521334884684-d80222895322?auto=format&fit=crop&w=700&q=80',
    'datas-especiais' => 'https://images.unsplash.com/photo-1487530811176-3780de880c2d?auto=format&fit=crop&w=700&q=80',
    'encomendas' => 'https://images.unsplash.com/photo-1455659817273-f96807779a8a?auto=format&fit=crop&w=700&q=80',
];
require_once __DIR__ . '/includes/header.php';
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
      <?php foreach (array_slice($categorias, 0, 4) as $categoria): ?>
        <?php
          $categorySlug = (string) ($categoria['slug'] ?? product_slugify((string) $categoria['nome'], 140));
          $categoryImage = (string) ($categoria['imagem'] ?? '');
          if ($categoryImage === '') {
              $categoryImage = $fallbackCategoryImages[$categorySlug] ?? reset($fallbackCategoryImages);
          }
          $categoryDescription = trim((string) ($categoria['descricao'] ?? ''));
          if ($categoryDescription === '') {
              $categoryDescription = 'Produtos selecionados para compor momentos especiais.';
          }
        ?>
        <a class="category-card" href="<?= site_url('catalogo.php?categoria=' . rawurlencode($categorySlug)) ?>">
          <img src="<?= e($categoryImage) ?>" alt="<?= e($categoria['nome']) ?>" loading="lazy">
          <span><?= e($categoria['nome']) ?></span>
          <p><?= e($categoryDescription) ?></p>
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
      <?php if (empty($destaques)): ?>
        <div class="empty-results">
          <strong>Nenhum produto disponível no momento.</strong>
          <p>Assim que o admin cadastrar e ativar produtos, eles aparecerão aqui automaticamente.</p>
        </div>
      <?php else: ?>
        <?php foreach (array_slice($destaques, 0, 6) as $produto): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container split-feature">
    <div>
      <span class="badge">Como funciona</span>
      <h2 class="section-title">Compra visual do início ao fim</h2>
      <p class="section-subtitle">A vitrine usa os produtos cadastrados no admin e mantém o fluxo comercial dentro do sistema.</p>
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
