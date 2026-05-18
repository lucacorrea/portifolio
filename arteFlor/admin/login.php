<?php require_once __DIR__ . '/../includes/helpers.php'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin | <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/admin-premium.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/premium-final.css') ?>">
</head>
<body class="admin-login-body">
<main class="admin-login-shell">
  <section class="admin-login-card card">
    <a class="brand" href="<?= site_url('index.php') ?>"><span class="brand-icon" aria-hidden="true">A&F</span><span>Arte<span>&</span>Flor</span></a>
    <div>
      <span class="badge">MVP visual</span>
      <h1 class="section-title">Painel administrativo</h1>
      <p>Entrada demonstrativa para apresentar dashboard, catálogo, PDV, pedidos e gestão comercial.</p>
    </div>

    <form action="<?= site_url('admin/dashboard.php') ?>" class="form-grid">
      <label class="form-group full"><span>Usuário demonstrativo</span><input type="email" value="admin@arteflor.demo" required></label>
      <label class="form-group full"><span>Senha demonstrativa</span><input type="password" value="arteflor123" required></label>
      <button class="btn btn-primary form-submit" type="submit">Entrar no painel</button>
    </form>

    <div class="admin-alert-card">
      <strong>Aviso importante</strong>
      Este MVP é apenas front-end. Não há autenticação real, banco de dados, pagamento real ou API conectada.
    </div>
  </section>
</main>
</body>
</html>
