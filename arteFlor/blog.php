<?php
$pageTitle = 'Blog';
$activePage = 'blog';
require_once __DIR__ . '/includes/header.php';

$posts = load_json('posts.json');
?>
<section class="page-hero" style="--page-image: url('https://images.unsplash.com/photo-1468327768560-75b778cbb551?auto=format&fit=crop&w=1600&q=80');">
  <div class="container">
    <span class="badge">Conteúdo demonstrativo</span>
    <h1 class="section-title">Blog Arte&Flor</h1>
    <p class="section-subtitle">Dicas de cuidado, ideias de presentes e conteúdos para fortalecer a presença digital da floricultura.</p>
  </div>
</section>

<section class="section">
  <div class="container grid-3">
    <?php foreach ($posts as $post): ?>
      <article class="card blog-card">
        <img src="<?= e($post['imagem']) ?>" alt="<?= e($post['alt']) ?>" loading="lazy">
        <div class="blog-card-body">
          <span class="eyebrow"><?= e($post['categoria']) ?></span>
          <h2><?= e($post['titulo']) ?></h2>
          <p class="muted"><?= e($post['resumo']) ?></p>
          <small class="muted"><?= e($post['data']) ?></small>
          <a class="btn btn-soft" href="<?= site_url('post.php?slug=' . rawurlencode($post['slug'])) ?>">Ler mais</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
