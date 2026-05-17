<?php
$pageTitle = 'Post';
$activePage = 'blog';
require_once __DIR__ . '/includes/header.php';

$slug = $_GET['slug'] ?? '';
$posts = load_json('posts.json');
$post = current(array_filter($posts, function ($item) use ($slug) {
    return ($item['slug'] ?? '') === $slug;
})) ?: ($posts[0] ?? []);
$relacionados = array_values(array_filter($posts, function ($item) use ($post) {
    return ($item['id'] ?? null) !== ($post['id'] ?? null);
}));
?>
<section class="page-hero">
  <div class="container">
    <span class="badge"><?= e($post['categoria'] ?? 'Blog') ?></span>
    <h1 class="section-title"><?= e($post['titulo'] ?? 'Blog Arte&Flor') ?></h1>
    <p class="section-subtitle"><?= e($post['resumo'] ?? '') ?></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <article class="post-content">
      <img class="post-hero-image" src="<?= e($post['imagem'] ?? '') ?>" alt="<?= e($post['alt'] ?? 'Imagem do post Arte&Flor') ?>" loading="lazy">
      <?php foreach (($post['conteudo'] ?? []) as $paragrafo): ?>
        <p><?= e($paragrafo) ?></p>
      <?php endforeach; ?>
      <div class="cta-band">
        <h2>Escolha flores para a próxima ocasião</h2>
        <p>O catálogo demonstrativo permite navegar por produtos, montar carrinho e enviar pedido pelo WhatsApp.</p>
        <div class="actions">
          <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Ver catálogo</a>
          <a class="btn btn-outline" href="<?= site_url('blog.php') ?>">Voltar ao blog</a>
        </div>
      </div>
    </article>
  </div>
</section>

<?php if (!empty($relacionados)): ?>
<section class="section">
  <div class="container">
    <div class="section-heading">
      <span class="eyebrow">Relacionados</span>
      <h2 class="section-title">Continue lendo</h2>
    </div>
    <div class="grid-3">
      <?php foreach (array_slice($relacionados, 0, 3) as $post): ?>
        <article class="card blog-card">
          <img src="<?= e($post['imagem']) ?>" alt="<?= e($post['alt']) ?>" loading="lazy">
          <div class="blog-card-body">
            <span class="eyebrow"><?= e($post['categoria']) ?></span>
            <h3><?= e($post['titulo']) ?></h3>
            <a class="btn btn-soft" href="<?= site_url('post.php?slug=' . rawurlencode($post['slug'])) ?>">Ler mais</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
