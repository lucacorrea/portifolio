<?php
$pageTitle = 'Blog';
$activePage = 'blog';
require_once __DIR__ . '/includes/header.php';
$posts = [
    ['slug' => 'como-conservar-flores-por-mais-tempo', 'categoria' => 'Cuidados', 'titulo' => 'Como conservar flores por mais tempo', 'resumo' => 'Pequenas rotinas que mantêm buquês bonitos e perfumados por mais dias.', 'imagem' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=900&q=80'],
    ['slug' => 'flores-ideais-para-aniversario', 'categoria' => 'Presentes', 'titulo' => 'Flores ideais para aniversário', 'resumo' => 'Como escolher cores, estilos e mensagens para cada perfil de presenteado.', 'imagem' => 'https://images.unsplash.com/photo-1487070183336-b863922373d4?auto=format&fit=crop&w=900&q=80'],
    ['slug' => 'presentes-florais-para-datas-especiais', 'categoria' => 'Datas especiais', 'titulo' => 'Presentes florais para datas especiais', 'resumo' => 'Cestas, arranjos e kits que deixam a comemoração mais memorável.', 'imagem' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=900&q=80'],
    ['slug' => 'como-escolher-um-buque-romantico', 'categoria' => 'Romântico', 'titulo' => 'Como escolher um buquê romântico', 'resumo' => 'Rosas, tons suaves e acabamentos que comunicam carinho com elegância.', 'imagem' => 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=900&q=80'],
];
?>
<section class="page-header editorial-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Blog Arte&Flor</span>
      <h1 class="section-title">Conteúdo para escolher e cuidar melhor</h1>
      <p class="section-subtitle">Posts fictícios para enriquecer a apresentação do catálogo e guiar clientes em datas especiais.</p>
    </div>
    <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Ver catálogo</a>
  </div>
</section>

<section class="section">
  <div class="container blog-grid">
    <?php foreach ($posts as $index => $post): ?>
      <article class="card post-card <?= $index === 0 ? 'featured-post' : '' ?>">
        <a class="post-image" href="<?= site_url('post.php?slug=' . rawurlencode($post['slug'])) ?>">
          <img src="<?= e($post['imagem']) ?>" alt="<?= e($post['titulo']) ?>" loading="lazy">
        </a>
        <div class="post-card-body">
          <span class="badge"><?= e($post['categoria']) ?></span>
          <h2><?= e($post['titulo']) ?></h2>
          <p><?= e($post['resumo']) ?></p>
          <a class="btn btn-soft" href="<?= site_url('post.php?slug=' . rawurlencode($post['slug'])) ?>">Ler artigo</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section section-band">
  <div class="container confidence-grid">
    <article>
      <span class="badge badge-rose">Próximo passo</span>
      <h2>Transforme a inspiração em pedido no catálogo.</h2>
    </article>
    <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Escolher flores</a>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
