<?php
declare(strict_types=1);

use App\Core\Application;

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];

try {
    $currentUser = $application->authorization()->requireLogin();
} catch (Throwable $exception) {
    $application->session()->flash('warning', 'Sua sessão expirou. Entre novamente.');
    header('Location: login.php', true, 303);
    exit;
}

http_response_code(403);

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
  <title>K. Yamaguchi — Acesso negado</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-panel">
      <div class="auth-brand">
        <div class="auth-icon"><i class="bi bi-shield-lock"></i></div>
        <div>
          <strong>K. Yamaguchi</strong>
          <span><?= h($currentUser->profileName()) ?></span>
        </div>
      </div>
      <h1>Acesso negado</h1>
      <p class="auth-subtitle">Você não possui permissão para acessar este recurso.</p>
      <div class="access-actions">
        <a class="auth-link-btn" href="dashboard.php">Ir para o dashboard</a>
        <button class="auth-link-btn muted" type="button" onclick="history.back()">Voltar</button>
      </div>
    </section>
  </main>
</body>
</html>
