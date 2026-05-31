<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/security/auth.php';
require_once __DIR__ . '/backend/security/csrf.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$email = '';
$next = $_GET['next'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');
    $next = $_POST['next'] ?? 'index.php';

    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        [$ok, $msg] = attemptLogin($email, $senha);

        if ($ok) {
            $safeNext = str_starts_with($next, '/') || str_contains($next, '://') ? 'index.php' : $next;
            header('Location: ' . $safeNext);
            exit;
        }

        $erro = $msg;
    }
}

$token = csrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#1657A7" />
  <title>Login | L&J Caixa</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
  <style>
    body {
      display: grid;
      place-items: center;
      min-height: 100vh;
      padding: 24px;
      background:
        radial-gradient(circle at 10% 0%, rgba(22,87,167,.12), transparent 32%),
        linear-gradient(135deg, #F6F9FE 0%, #EDF3FB 100%);
    }

    .login-shell {
      width: min(100%, 430px);
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 30px;
      padding: 28px;
      box-shadow: 0 20px 55px rgba(29,55,95,.12);
    }

    .login-brand {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 24px;
    }

    .login-brand img {
      width: 54px;
      height: 54px;
      border-radius: 18px;
      background: var(--blue);
    }

    .login-brand h1 {
      margin: 0;
      font-size: 26px;
      letter-spacing: -.06em;
      color: var(--ink);
    }

    .login-brand p {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 13px;
      font-weight: 700;
    }

    .login-error {
      margin-bottom: 14px;
      padding: 12px 14px;
      border-radius: 16px;
      color: var(--red);
      background: rgba(230,83,103,.10);
      font-size: 13px;
      font-weight: 800;
    }

    .login-help {
      margin-top: 18px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.45;
    }
  </style>
</head>
<body>
  <main class="login-shell">
    <div class="login-brand">
      <img src="assets/icons/icon.svg" alt="L&J" />
      <div>
        <h1>L&J Caixa</h1>
        <p>Gestão comercial premium</p>
      </div>
    </div>

    <?php if ($erro): ?>
      <div class="login-error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="form-grid" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" placeholder="admin@ljsolucoestech.com.br">
      </div>

      <div class="field">
        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" required placeholder="Digite sua senha">
      </div>

      <button class="primary-btn" type="submit">Entrar no sistema</button>
    </form>

    <p class="login-help">
      Acesso inicial após importar o SQL:<br>
      <strong>E-mail:</strong> admin@ljsolucoestech.com.br<br>
      <strong>Senha:</strong> Admin@123<br>
      Troque essa senha depois do primeiro acesso.
    </p>
  </main>
</body>
</html>
