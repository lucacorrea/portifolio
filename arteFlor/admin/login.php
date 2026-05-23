<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';
$login = '';
$next = admin_sanitize_next($_GET['next'] ?? $_POST['next'] ?? null);

if (admin_is_authenticated()) {
    header('Location: ' . $next);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif (admin_login_is_rate_limited($login)) {
        http_response_code(429);
        $error = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
    } elseif (admin_login($login, $password)) {
        header('Location: ' . $next);
        exit;
    } else {
        $error = 'E-mail ou senha inválidos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin | <?= SITE_NAME ?></title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/adminStyle.css') ?>">
</head>
<body class="admin-login-body">
<main class="admin-login-shell">
  <section class="admin-login-card card">
    <a class="brand" href="<?= site_url('index.php') ?>"><span class="brand-icon" aria-hidden="true">A&F</span><span>Arte<span>&</span>Flor</span></a>
    <div>
      <span class="badge">Acesso protegido</span>
      <h1 class="section-title">Painel administrativo</h1>
      <p>Entre com um usuário ativo para gerenciar catálogo, PDV, pedidos e operação.</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="admin-alert-card admin-alert-danger" role="alert">
        <strong>Não foi possível entrar</strong>
        <?= e($error) ?>
      </div>
    <?php elseif (isset($_GET['logged_out'])): ?>
      <div class="admin-alert-card admin-alert-success" role="status">
        <strong>Sessão encerrada</strong>
        Entre novamente para acessar o painel.
      </div>
    <?php endif; ?>

    <form action="<?= site_url('admin/login.php') ?>" method="post" class="form-grid" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <label class="form-group full">
        <span>E-mail ou nome</span>
        <input type="text" name="login" value="<?= e($login) ?>" autocomplete="username" required autofocus>
      </label>
      <label class="form-group full">
        <span>Senha</span>
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button class="btn btn-primary form-submit" type="submit">Entrar no painel</button>
    </form>

    <div class="admin-alert-card admin-alert-info">
      <strong>Segurança</strong>
      O acesso usa sessão segura, CSRF e verificação de senha com hash no banco.
    </div>
  </section>
</main>
</body>
</html>
