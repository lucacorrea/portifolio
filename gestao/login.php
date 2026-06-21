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
  <meta name="theme-color" content="#173F5F" />
  <title>Login | Sistema de Gestão</title>

  <style>
    :root {
      --primary: #173f5f;
      --primary-2: #1f5f8b;
      --primary-dark: #102f48;
      --accent: #88b8cc;

      --bg: #f7f8fa;
      --card: #ffffff;
      --input: #f3f5fa;
      --text: #17324d;
      --muted: #8999aa;
      --border: rgba(23, 63, 95, 0.08);

      --danger: #b42318;
      --danger-bg: #fff1f0;
      --danger-border: #ffccc7;

      --radius-xl: 34px;
      --shadow-card: 0 30px 80px rgba(23, 63, 95, 0.18);
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      min-height: 100%;
    }

    body {
      margin: 0;
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text);
      background: var(--bg);
    }

    body.login-page {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 32px 18px;
      overflow-x: hidden;
      position: relative;
    }

    body.login-page::before {
      content: "";
      position: fixed;
      width: min(60vw, 640px);
      height: min(60vw, 640px);
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      border-radius: 50%;
      background:
        linear-gradient(145deg,
          rgba(23, 63, 95, 0.88) 0%,
          rgba(31, 95, 139, 0.58) 48%,
          rgba(136, 184, 204, 0.28) 100%);
      z-index: 0;
      pointer-events: none;
    }

    body.login-page::after {
      content: "";
      position: fixed;
      width: 42px;
      height: 42px;
      left: 8%;
      top: 12%;
      border-radius: 50%;
      background: var(--primary-2);
      opacity: 0.9;
      box-shadow:
        68vw 58vh 0 -8px rgba(136, 184, 204, 0.9),
        78vw 11vh 0 -15px rgba(23, 63, 95, 0.26),
        15vw 72vh 0 -17px rgba(23, 63, 95, 0.20);
      z-index: 0;
      pointer-events: none;
    }

    .login-card {
      position: relative;
      z-index: 1;
      width: min(100%, 430px);
      min-height: 520px;
      padding: 42px 34px 30px;
      background: rgba(255, 255, 255, 0.97);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-card);
      display: flex;
      flex-direction: column;
      animation: cardIn 0.45s ease both;
    }

    @keyframes cardIn {
      from {
        opacity: 0;
        transform: translateY(18px) scale(0.98);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .login-brand {
      margin: 0 0 30px;
      display: grid;
      gap: 10px;
    }

    .login-brand h1 {
      margin: 0;
      color: var(--text);
      font-size: 2.35rem;
      line-height: 1;
      letter-spacing: -0.06em;
      font-weight: 950;
    }

    .login-brand p {
      margin: 0;
      max-width: 310px;
      color: var(--muted);
      font-size: 0.96rem;
      font-weight: 700;
      line-height: 1.45;
    }

    .login-error {
      margin: 0 0 18px;
      padding: 12px 14px;
      color: var(--danger);
      background: var(--danger-bg);
      border: 1px solid var(--danger-border);
      border-radius: 14px;
      font-size: 0.88rem;
      font-weight: 700;
      line-height: 1.4;
    }

    .login-form {
      display: grid;
      gap: 15px;
      width: 100%;
    }

    .field {
      display: grid;
      gap: 7px;
    }

    .field-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .field label {
      color: var(--text);
      font-size: 0.8rem;
      font-weight: 850;
      padding-left: 4px;
    }

    .field input {
      width: 100%;
      height: 52px;
      padding: 0 17px;
      border: 1px solid transparent;
      border-radius: 999px;
      outline: none;
      color: var(--text);
      background: var(--input);
      font-size: 0.94rem;
      font-weight: 700;
      transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
    }

    .field input::placeholder {
      color: #a1acb8;
      font-weight: 600;
    }

    .field input:hover {
      background: #edf1f7;
    }

    .field input:focus {
      background: #ffffff;
      border-color: rgba(31, 95, 139, 0.36);
      box-shadow: 0 0 0 5px rgba(31, 95, 139, 0.10);
    }

    .forgot-link {
      color: var(--primary-2);
      text-decoration: none;
      font-size: 0.78rem;
      font-weight: 850;
      white-space: nowrap;
      transition: color 0.2s ease;
    }

    .forgot-link:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }

    .primary-btn {
      width: 100%;
      height: 54px;
      margin-top: 6px;
      border: none;
      border-radius: 999px;
      background: linear-gradient(135deg, var(--primary-dark), var(--primary));
      color: #ffffff;
      cursor: pointer;
      font-size: 0.92rem;
      font-weight: 900;
      letter-spacing: -0.01em;
      box-shadow: 0 16px 32px rgba(23, 63, 95, 0.24);
      transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        filter 0.2s ease;
    }

    .primary-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 20px 38px rgba(23, 63, 95, 0.30);
      filter: brightness(1.05);
    }

    .primary-btn:active {
      transform: translateY(0);
      box-shadow: 0 10px 22px rgba(23, 63, 95, 0.22);
    }

    .primary-btn:focus-visible,
    .forgot-link:focus-visible {
      outline: 4px solid rgba(31, 95, 139, 0.18);
      outline-offset: 3px;
      border-radius: 10px;
    }

    .login-help {
      margin-top: 18px;
      padding: 14px 16px;
      display: grid;
      gap: 5px;
      border-radius: 18px;
      background: rgba(243, 245, 250, 0.82);
      border: 1px solid rgba(23, 63, 95, 0.06);
      color: var(--muted);
    }

    .login-help strong {
      color: var(--text);
      font-size: 0.82rem;
      font-weight: 900;
    }

    .login-help span {
      font-size: 0.8rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .login-footer {
      margin-top: auto;
      padding-top: 22px;
      text-align: center;
      color: var(--muted);
      font-size: 0.78rem;
      font-weight: 700;
    }

    .login-footer strong {
      color: var(--text);
      font-weight: 900;
    }

    @media (min-width: 980px) {
      .login-card {
        transform: translateX(-72px);
      }

      body.login-page::before {
        transform: translate(-50%, -50%) translateX(28px);
      }
    }

    @media (max-width: 768px) {
      body.login-page {
        overflow-y: auto;
        padding: 28px 16px;
      }

      body.login-page::before {
        width: 520px;
        height: 520px;
        top: 44%;
      }

      .login-card {
        width: min(100%, 390px);
        min-height: auto;
        padding: 36px 28px 28px;
        border-radius: 30px;
      }
    }

    @media (max-width: 480px) {
      body.login-page {
        padding: 18px 12px;
      }

      body.login-page::before {
        width: 420px;
        height: 420px;
        top: 43%;
      }

      body.login-page::after {
        width: 32px;
        height: 32px;
        left: 7%;
        top: 8%;
      }

      .login-card {
        width: min(100%, 360px);
        padding: 32px 22px 24px;
        border-radius: 28px;
      }

      .login-brand {
        margin-bottom: 26px;
      }

      .login-brand h1 {
        font-size: 2rem;
      }

      .login-brand p {
        font-size: 0.86rem;
      }

      .field input,
      .primary-btn {
        height: 50px;
      }

      .field-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
      }
    }

    @media (max-width: 360px) {
      .login-card {
        padding-inline: 18px;
      }

      .login-brand h1 {
        font-size: 1.78rem;
      }
    }
    .login-footer {
  margin-top: auto;
  padding-top: 24px;
  text-align: center;
  display: grid;
  gap: 4px;
  color: var(--muted);
  font-size: 0.76rem;
  font-weight: 700;
}

.login-footer a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 900;
  transition:
    color 0.2s ease,
    opacity 0.2s ease;
}

.login-footer a:hover {
  color: var(--primary-dark);
  text-decoration: underline;
}

.login-footer a:focus-visible {
  outline: 4px solid rgba(31, 95, 139, 0.18);
  outline-offset: 3px;
  border-radius: 8px;
}

.login-footer small {
  display: block;
  color: #a0abba;
  font-size: 0.72rem;
  font-weight: 700;
}
  </style>
</head>

<body class="login-page">
  <main class="login-card" aria-labelledby="loginTitle">
    <header class="login-brand">
      <h1 id="loginTitle">Login</h1>
      <p>Acesse o Sistema de Gestão para gerenciar vendas, caixa e operações.</p>
    </header>

    <?php if ($error): ?>
      <div class="login-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <div class="field">
        <label for="email">E-mail</label>
        <input
          id="email"
          name="email"
          type="email"
          required
          value="<?= e($email) ?>"
          placeholder="admin@ljsolucoestech.com.br"
          autocomplete="email">
      </div>

      <div class="field">
        <div class="field-row">
          <label for="senha">Senha</label>
          <a class="forgot-link" href="forgot-password.php">Esqueci minha senha</a>
        </div>

        <input
          id="senha"
          name="senha"
          type="password"
          required
          placeholder="Digite sua senha"
          autocomplete="current-password">
      </div>

      <button class="primary-btn" type="submit">Entrar no sistema</button>
    </form>


    <footer class="login-footer">
      <span>
        Desenvolvido por
        <a href="https://ljsolucoestech.com.br" target="_blank" rel="noopener noreferrer">
          Sistema de Gestão
        </a>
      </span>
      <small>Gestão comercial premium</small>
    </footer>
  </main>
</body>

</html>
