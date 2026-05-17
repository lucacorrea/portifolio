<?php require_once __DIR__ . '/../includes/helpers.php'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin | Arte&Flor</title>
  <meta name="description" content="Tela administrativa demonstrativa da Arte&Flor.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body>
  <main class="login-page">
    <form class="card login-card form-grid" action="<?= site_url('admin/dashboard.php') ?>" data-demo-form>
      <a class="brand" href="<?= site_url('index.php') ?>" aria-label="Arte&Flor - início">
        <span class="brand-mark" aria-hidden="true">A&F</span>
        <span class="brand-text">Arte<span>&</span>Flor</span>
      </a>
      <div>
        <span class="badge">Área administrativa</span>
        <h1 class="section-title">Entrar</h1>
      
      </div>
      <label class="form-group full">
        <span>E-mail</span>
        <input type="email" placeholder="admin@arteflor.com" required>
      </label>
      <label class="form-group full">
        <span>Senha</span>
        <input type="password" placeholder="********" required>
      </label>
      <button class="btn btn-primary full" type="submit">Entrar no painel</button>
    </form>
  </main>
  <div class="toast" data-toast role="status" aria-live="polite"></div>
  <script src="<?= asset('js/app.js') ?>"></script>
  <script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
