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
<style>
  :root {
    --primary: #1657A7;
    --primary-dark: #0E4384;
    --primary-soft: #EAF2FF;
    --bg: #F3F7FE;
    --bg-2: #EAF1FA;
    --card: #FFFFFF;
    --text: #102A43;
    --muted: #7284A0;
    --border: #DCE7F5;
    --danger: #B42318;
    --danger-bg: #FEF3F2;
    --danger-border: #FDA29B;
    --radius: 24px;
    --radius-md: 16px;
    --shadow: 0 24px 70px rgba(22, 87, 167, 0.16);
    --shadow-soft: 0 12px 32px rgba(16, 42, 67, 0.08);
  }

  * {
    box-sizing: border-box;
  }

  html {
    width: 100%;
    min-height: 100%;
    scroll-behavior: smooth;
  }

  body {
    margin: 0;
    width: 100%;
    min-height: 100vh;
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    color: var(--text);
    background:
      radial-gradient(circle at 18% 18%, rgba(22, 87, 167, 0.08), transparent 34%),
      radial-gradient(circle at 86% 76%, rgba(22, 87, 167, 0.10), transparent 30%),
      linear-gradient(135deg, #F8FBFF 0%, var(--bg) 42%, var(--bg-2) 100%);
  }

  body.login-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 32px 18px;
    overflow-x: hidden;
  }

  .login-card {
    width: min(100%, 760px);
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(220, 231, 245, 0.92);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 34px;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    animation: loginFadeIn 0.45s ease both;
  }

  @keyframes loginFadeIn {
    from {
      opacity: 0;
      transform: translateY(14px) scale(0.985);
    }

    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  .login-brand {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
  }

  .login-brand img {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    display: block;
    border-radius: 17px;
    box-shadow: 0 12px 26px rgba(22, 87, 167, 0.18);
  }

  .login-brand h1 {
    margin: 0;
    color: #0B2A4A;
    font-size: clamp(1.65rem, 3vw, 2.05rem);
    font-weight: 850;
    line-height: 1.05;
    letter-spacing: -0.045em;
  }

  .login-brand p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 0.94rem;
    font-weight: 700;
    letter-spacing: 0.01em;
  }

  .login-error {
    width: 100%;
    margin: 0 0 20px;
    padding: 13px 15px;
    border-radius: 14px;
    border: 1px solid var(--danger-border);
    background: var(--danger-bg);
    color: var(--danger);
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1.45;
  }

  .login-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 220px;
    align-items: end;
    gap: 16px;
    width: 100%;
  }

  .field {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .field label {
    color: #0B2A4A;
    font-size: 0.86rem;
    font-weight: 850;
    letter-spacing: -0.01em;
  }

  .field input {
    width: 100%;
    height: 58px;
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    background: #EAF2FF;
    color: #0B2A4A;
    padding: 0 17px;
    font-size: 0.98rem;
    font-weight: 600;
    outline: none;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      background-color 0.2s ease,
      transform 0.2s ease;
  }

  .field input::placeholder {
    color: #7F92AD;
    font-weight: 500;
  }

  .field input:hover {
    background: #E5EFFD;
  }

  .field input:focus {
    background: #FFFFFF;
    border-color: rgba(22, 87, 167, 0.62);
    box-shadow: 0 0 0 4px rgba(22, 87, 167, 0.12);
  }

  .primary-btn {
    width: 100%;
    height: 58px;
    border: 0;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--primary) 0%, #1E66BE 100%);
    color: #FFFFFF;
    cursor: pointer;
    font-size: 0.98rem;
    font-weight: 850;
    letter-spacing: -0.015em;
    box-shadow: 0 16px 30px rgba(22, 87, 167, 0.24);
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease,
      filter 0.2s ease;
  }

  .primary-btn:hover {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
    box-shadow: 0 18px 34px rgba(22, 87, 167, 0.30);
    transform: translateY(-1px);
  }

  .primary-btn:active {
    transform: translateY(0);
    box-shadow: 0 10px 20px rgba(22, 87, 167, 0.22);
  }

  .primary-btn:focus-visible {
    outline: 4px solid rgba(22, 87, 167, 0.20);
    outline-offset: 3px;
  }

  .login-help {
    margin-top: 22px;
    display: grid;
    gap: 6px;
    padding: 15px 16px 15px 18px;
    border: 1px solid var(--border);
    border-left: 4px solid var(--primary);
    border-radius: 16px;
    background: linear-gradient(135deg, #F8FBFF 0%, #ECF4FF 100%);
    color: var(--muted);
    box-shadow: var(--shadow-soft);
  }

  .login-help strong {
    color: #24445F;
    font-size: 0.91rem;
    font-weight: 850;
    margin-bottom: 2px;
  }

  .login-help span {
    font-size: 0.88rem;
    line-height: 1.35;
    font-weight: 600;
  }

  .login-help span::first-letter {
    color: var(--primary);
  }

  @media (max-width: 1024px) {
    body.login-page {
      padding: 28px 18px;
    }

    .login-card {
      width: min(100%, 700px);
      padding: 32px;
    }

    .login-form {
      grid-template-columns: 1fr 1fr;
    }

    .primary-btn {
      grid-column: 1 / -1;
    }
  }

  @media (max-width: 768px) {
    body.login-page {
      align-items: center;
      padding: 24px 16px;
    }

    .login-card {
      width: min(100%, 520px);
      padding: 28px;
      border-radius: 22px;
    }

    .login-form {
      grid-template-columns: 1fr;
      gap: 15px;
    }

    .login-brand {
      margin-bottom: 24px;
    }

    .login-brand img {
      width: 54px;
      height: 54px;
      flex-basis: 54px;
    }

    .primary-btn {
      margin-top: 2px;
    }
  }

  @media (max-width: 480px) {
    body.login-page {
      padding: 18px 12px;
      place-items: center;
    }

    .login-card {
      padding: 22px;
      border-radius: 20px;
    }

    .login-brand {
      gap: 13px;
      align-items: center;
      margin-bottom: 22px;
    }

    .login-brand img {
      width: 50px;
      height: 50px;
      flex-basis: 50px;
      border-radius: 15px;
    }

    .login-brand h1 {
      font-size: 1.52rem;
    }

    .login-brand p {
      font-size: 0.84rem;
      line-height: 1.25;
    }

    .field input,
    .primary-btn {
      height: 54px;
      border-radius: 15px;
    }

    .field input {
      padding-inline: 15px;
      font-size: 0.94rem;
    }

    .primary-btn {
      font-size: 0.95rem;
    }

    .login-help {
      margin-top: 18px;
      padding: 14px;
      border-radius: 15px;
    }

    .login-help span,
    .login-help strong {
      font-size: 0.84rem;
    }
  }

  @media (max-width: 360px) {
    .login-card {
      padding: 18px;
    }

    .login-brand {
      align-items: flex-start;
    }

    .login-brand h1 {
      font-size: 1.38rem;
    }
  }
</style>
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
