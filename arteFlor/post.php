<?php
$pageTitle = 'Artigo';
$activePage = 'blog';
require_once __DIR__ . '/includes/header.php';
$posts = [
    'como-conservar-flores-por-mais-tempo' => [
        'categoria' => 'Cuidados',
        'titulo' => 'Como conservar flores por mais tempo',
        'imagem' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=1200&q=80',
        'texto' => [
            'Troque a água diariamente e lave o vaso antes de recolocar as flores. Esse cuidado reduz resíduos e ajuda o buquê a permanecer bonito por mais tempo.',
            'Mantenha o arranjo longe de sol direto, vento forte e calor excessivo. Flores naturais respondem melhor a ambientes frescos e iluminados indiretamente.',
            'Remova folhas que ficam submersas e corte a ponta das hastes em diagonal. Isso melhora a absorção de água e deixa a composição com aparência mais leve.'
        ]
    ],
    'flores-ideais-para-aniversario' => [
        'categoria' => 'Presentes',
        'titulo' => 'Flores ideais para aniversário',
        'imagem' => 'https://images.unsplash.com/photo-1487070183336-b863922373d4?auto=format&fit=crop&w=1200&q=80',
        'texto' => [
            'Para aniversários, cores vivas comunicam celebração e energia. Tons pastel funcionam bem quando a intenção é transmitir delicadeza.',
            'Arranjos em vaso são ótimos para quem quer um presente decorativo e fácil de acomodar em casa ou no trabalho.',
            'Uma mensagem curta no cartão personaliza o presente e torna a entrega mais memorável.'
        ]
    ],
    'presentes-florais-para-datas-especiais' => [
        'categoria' => 'Datas especiais',
        'titulo' => 'Presentes florais para datas especiais',
        'imagem' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=1200&q=80',
        'texto' => [
            'Datas especiais pedem presentes com acabamento cuidadoso. Cestas com flores, cartões e pequenos mimos ajudam a criar uma experiência completa.',
            'Para entregas surpresa, confirme endereço, ponto de referência e horário desejado para reduzir desencontros.',
            'Produtos sob encomenda são indicados quando há preferência por cor, estilo ou uma composição mais exclusiva.'
        ]
    ],
    'como-escolher-um-buque-romantico' => [
        'categoria' => 'Romântico',
        'titulo' => 'Como escolher um buquê romântico',
        'imagem' => 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=1200&q=80',
        'texto' => [
            'Rosas vermelhas são clássicas para ocasiões românticas, mas tons suaves também funcionam quando a proposta é leve e elegante.',
            'O tamanho do buquê deve combinar com o momento. Mini buquês funcionam para gestos delicados; composições maiores criam impacto.',
            'Finalize com um cartão simples e sincero. A mensagem é parte importante da experiência de presente.'
        ]
    ],
];
$slug = $_GET['slug'] ?? 'como-conservar-flores-por-mais-tempo';
$post = $posts[$slug] ?? reset($posts);
?>
<article class="section">
  <div class="container article-layout">
    <header class="article-header">
      <span class="badge"><?= e($post['categoria']) ?></span>
      <h1 class="section-title"><?= e($post['titulo']) ?></h1>
      <p class="section-subtitle">Conteúdo demonstrativo para apoiar a experiência editorial da floricultura.</p>
    </header>
    <figure class="post-cover">
      <img src="<?= e($post['imagem']) ?>" alt="<?= e($post['titulo']) ?>">
    </figure>
    <div class="article-body card">
      <?php foreach ($post['texto'] as $paragraph): ?>
        <p><?= e($paragraph) ?></p>
      <?php endforeach; ?>
      <div class="actions">
        <a class="btn btn-primary" href="<?= site_url('catalogo.php') ?>">Ver catálogo</a>
        <a class="btn btn-soft" href="<?= site_url('blog.php') ?>">Voltar ao blog</a>
      </div>
    </div>
  </div>
</article>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
