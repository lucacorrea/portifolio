<?php
declare(strict_types=1);

use App\Core\Application;

header('Cache-Control: no-store');
header('Pragma: no-cache');

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();
$csrf = $application->csrf();
$redirect = $application->redirect();
$next = $redirect->sanitize($_GET['next'] ?? 'dashboard.php');

try {
    if ($application->authentication()->isAuthenticated()) {
        header('Location: ' . $redirect->applicationUrl($next), true, 303);
        exit;
    }
} catch (Throwable $exception) {
}

$messages = $session->consumeFlashMessages();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>K. Yamaguchi — Acesso ao sistema</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-panel" aria-labelledby="login-title">
      <div class="auth-brand">
        <div class="auth-icon"><i class="bi bi-snow2"></i></div>
        <div>
          <strong>K. Yamaguchi</strong>
          <span>Gestão de Serviços</span>
        </div>
      </div>

      <h1 id="login-title">Acesso ao sistema</h1>
      <p class="auth-subtitle">Informe suas credenciais para continuar.</p>

      <?php foreach ($messages as $message): ?>
        <div class="auth-alert auth-alert-<?= h((string) $message['type']) ?>" role="alert">
          <?= h((string) $message['message']) ?>
        </div>
      <?php endforeach; ?>

      <form class="auth-form" method="post" action="actions/login.php" novalidate data-auth-form>
        <?= $csrf->field() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">

        <div class="auth-field">
          <label for="identifier">Usuário ou e-mail</label>
          <input id="identifier" name="identifier" type="text" autocomplete="username" maxlength="150" required autofocus>
        </div>

        <div class="auth-field">
          <label for="password">Senha</label>
          <div class="password-wrap">
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="button" data-password-toggle aria-label="Mostrar senha">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <button class="auth-submit" type="submit">
          <span>Entrar</span>
          <i class="bi bi-arrow-right"></i>
        </button>
      </form>
    </section>
  </main>
  <script src="assets/js/auth.js"></script>
</body>
</html>
