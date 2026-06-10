<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Config;
use App\Security\Auth;
use App\Security\Csrf;

if (Auth::check()) {
    Response::redirect('index.php');
}

$request = new Request();
$error = '';
$email = '';
$next = (string)$request->query('next', 'index.php');

if ($request->isPost()) {
    $email = trim((string)$request->post('email', ''));
    $senha = (string)$request->post('senha', '');
    $next = (string)$request->post('next', 'index.php');

    if (!Csrf::validate((string)$request->post('csrf_token', ''))) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        [$ok, $message, $user] = Auth::attempt($email, $senha);

        if ($ok && $user) {
            Auth::login($user);

            $safeNext = str_starts_with($next, '/') || str_contains($next, '://') ? 'index.php' : $next;
            Response::redirect($safeNext);
        }

        $error = $message;
    }
}

$token = Csrf::token();
$appConfig = Config::app();
$showInitialAccess = ($appConfig['env'] ?? 'production') !== 'production' || (bool)($appConfig['debug'] ?? false);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#1657A7" />
  <title>Login | L&J Caixa</title>
  <link rel="stylesheet" href="assets/css/main.css" />
</head>
<body class="login-page">
  <main class="login-card" aria-labelledby="loginTitle">
    <header class="login-brand">
      <img src="assets/icons/icon.svg" alt="L&J" />
      <div>
        <h1 id="loginTitle">L&J Caixa</h1>
        <p>Gestão comercial premium</p>
      </div>
    </header>

    <?php if ($error): ?>
      <div class="login-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <div class="field">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="admin@ljsolucoestech.com.br">
      </div>

      <div class="field">
        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" required placeholder="Digite sua senha">
      </div>

      <button class="primary-btn" type="submit">Entrar no sistema</button>
    </form>

    <?php if ($showInitialAccess): ?>
      <aside class="login-help" aria-label="Acesso inicial de desenvolvimento">
        <strong>Acesso inicial após importar o SQL</strong>
        <span>E-mail: admin@ljsolucoestech.com.br</span>
        <span>Senha: Admin@123</span>
      </aside>
    <?php endif; ?>
  </main>
</body>
</html>
