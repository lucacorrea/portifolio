<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$helpers = __DIR__ . '/assets/dados/_helpers.php';
if (is_file($helpers)) {
  require_once $helpers;
}

/* =========================
   FALLBACKS
========================= */
if (!function_exists('e')) {
  function e(string $value): string
  {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

if (!function_exists('csrf_token')) {
  function csrf_token(): string
  {
    if (empty($_SESSION['_csrf_token'])) {
      $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf_token'];
  }
}

if (!function_exists('csrf_input')) {
  function csrf_input(): string
  {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
  }
}

if (!function_exists('flash_pop')) {
  function flash_pop(string $key, string $default = ''): string
  {
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : $default;
  }
}

if (!function_exists('old')) {
  function old(string $key, string $default = ''): string
  {
    $data = $_SESSION['_old'] ?? [];
    if (!is_array($data)) return $default;
    $value = $data[$key] ?? $default;
    return is_scalar($value) ? (string)$value : $default;
  }
}

if (!function_exists('old_clear')) {
  function old_clear(): void
  {
    unset($_SESSION['_old']);
  }
}

/* =========================
   SE JÁ ESTIVER LOGADO
========================= */
if (function_exists('auth_check') && auth_check()) {
  header('Location: dashboard.php');
  exit;
}

/* =========================
   FLASH / OLD
========================= */
$loginErro = flash_pop('auth_erro');
$loginOk   = flash_pop('auth_ok');

$emailOld = old('email');
old_clear();

$emailCookie = isset($_COOKIE['plhb_login_email']) && is_string($_COOKIE['plhb_login_email'])
  ? trim($_COOKIE['plhb_login_email'])
  : '';

$emailValue = $emailOld !== '' ? $emailOld : $emailCookie;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Entrar | Painel da Distribuidora PLHB</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    :root {
      --plhb-primary: #4f46e5;
      --plhb-primary-dark: #4338ca;
      --plhb-soft: #eef2ff;
      --plhb-text: #1f2937;
      --plhb-muted: #6b7280;
      --plhb-border: #e5e7eb;
      --plhb-bg: #f3f4f6;
      --plhb-danger-bg: #fef2f2;
      --plhb-danger-border: #fecaca;
      --plhb-danger-text: #991b1b;
      --plhb-success-bg: #ecfdf5;
      --plhb-success-border: #a7f3d0;
      --plhb-success-text: #065f46;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      background: var(--plhb-bg);
      font-family: inherit;
      overflow-x: hidden;
    }

    .main-wrapper {
      margin-left: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      min-height: 100vh;
    }

    .signin-section {
      min-height: 100vh;
      padding: 0;
      display: flex;
      align-items: stretch;
    }

    .signin-section .container-fluid {
      padding: 0;
    }

    .auth-row {
      min-height: 100vh;
      margin: 0;
    }

    .auth-left,
    .auth-right {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .auth-left {
      background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
      position: relative;
      overflow: hidden;
      padding: 40px;
    }

    .auth-left::before {
      content: "";
      position: absolute;
      width: 320px;
      height: 320px;
      background: rgba(79, 70, 229, 0.08);
      border-radius: 50%;
      top: -80px;
      right: -80px;
    }

    .auth-left::after {
      content: "";
      position: absolute;
      width: 260px;
      height: 260px;
      background: rgba(79, 70, 229, 0.08);
      border-radius: 50%;
      bottom: -80px;
      left: -80px;
    }

    .auth-cover-content {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 700px;
      text-align: center;
    }

    .auth-cover-content h1 {
      font-size: 2.6rem;
      line-height: 1.2;
      font-weight: 700;
      color: var(--plhb-primary);
      margin-bottom: 14px;
    }

    .auth-cover-content p {
      font-size: 1rem;
      color: var(--plhb-muted);
      margin-bottom: 30px;
    }

    .cover-image img {
      width: 100%;
      max-width: 500px;
      height: auto;
      display: block;
      margin: 0 auto;
    }

    .auth-right {
      background: #ffffff;
      padding: 40px;
    }

    .signin-wrapper {
      width: 100%;
      max-width: 520px;
    }

    .form-wrapper {
      width: 100%;
      background: #fff;
      border-radius: 18px;
      padding: 10px 0;
    }

    .login-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--plhb-soft);
      color: var(--plhb-primary-dark);
      border: 1px solid #c7d2fe;
      padding: 8px 14px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .form-title {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--plhb-text);
      margin-bottom: 10px;
    }

    .form-subtitle {
      color: var(--plhb-muted);
      font-size: 0.98rem;
      line-height: 1.6;
      margin-bottom: 30px;
    }

    .input-style-1 {
      margin-bottom: 22px;
    }

    .input-style-1 label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--plhb-text);
      margin-bottom: 8px;
    }

    .input-style-1 input {
      width: 100%;
      height: 56px;
      border: 1px solid var(--plhb-border);
      border-radius: 12px;
      background: #f8fafc;
      padding: 0 16px;
      font-size: 15px;
      color: var(--plhb-text);
      transition: all 0.25s ease;
      outline: none;
      box-shadow: none;
    }

    .input-style-1 input:focus {
      border-color: var(--plhb-primary);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
    }

    .checkbox-style {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 0;
    }

    .checkbox-style .form-check-input {
      width: 18px;
      height: 18px;
      margin-top: 0;
      border-radius: 4px;
      cursor: pointer;
    }

    .checkbox-style .form-check-label {
      font-size: 14px;
      color: var(--plhb-muted);
      cursor: pointer;
    }

    .forgot-link {
      font-size: 14px;
      color: var(--plhb-primary);
      text-decoration: none;
      font-weight: 600;
      transition: 0.2s ease;
    }

    .forgot-link:hover {
      color: var(--plhb-primary-dark);
      text-decoration: underline;
    }

    .main-btn.primary-btn {
      width: 100%;
      height: 56px;
      border: none;
      border-radius: 12px;
      background: linear-gradient(90deg, var(--plhb-primary) 0%, #5b6df6 100%);
      color: #fff;
      font-size: 16px;
      font-weight: 700;
      transition: all 0.25s ease;
      box-shadow: 0 10px 25px rgba(79, 70, 229, 0.22);
    }

    .main-btn.primary-btn:hover {
      transform: translateY(-1px);
      background: linear-gradient(90deg, var(--plhb-primary-dark) 0%, var(--plhb-primary) 100%);
      color: #fff;
    }

    .login-footer-info {
      margin-top: 28px;
      padding-top: 18px;
      border-top: 1px solid var(--plhb-border);
      text-align: center;
    }

    .login-footer-info p {
      margin: 0;
      font-size: 14px;
      color: var(--plhb-muted);
    }

    .login-footer-info strong {
      color: var(--plhb-text);
    }

    .alert-custom {
      width: 100%;
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 18px;
      font-size: 14px;
      line-height: 1.6;
      border: 1px solid transparent;
    }

    .alert-danger-custom {
      background: var(--plhb-danger-bg);
      border-color: var(--plhb-danger-border);
      color: var(--plhb-danger-text);
    }

    .alert-success-custom {
      background: var(--plhb-success-bg);
      border-color: var(--plhb-success-border);
      color: var(--plhb-success-text);
    }

    #preloader {
      position: fixed;
      inset: 0;
      z-index: 99999;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    #preloader .spinner {
      width: 42px;
      height: 42px;
      border: 4px solid #e5e7eb;
      border-top-color: var(--plhb-primary);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    @media (max-width: 991.98px) {
      .auth-left {
        display: none;
      }

      .auth-right {
        min-height: 100vh;
        padding: 30px 20px;
      }

      .signin-wrapper {
        max-width: 100%;
      }

      .form-title {
        font-size: 1.55rem;
      }
    }

    @media (max-width: 575.98px) {
      .auth-right {
        padding: 24px 16px;
      }

      .form-wrapper {
        padding: 0;
      }

      .input-style-1 input,
      .main-btn.primary-btn {
        height: 52px;
      }
    }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="spinner"></div>
  </div>

  <main class="main-wrapper">
    <section class="signin-section">
      <div class="container-fluid">
        <div class="row g-0 auth-row">
          <div class="col-lg-6 auth-left">
            <div class="auth-cover-content">
              <h1>Seja Bem-Vindo</h1>
              <p>
                Faça login para acessar o <strong>Painel da Distribuidora PLHB</strong>
                e continuar gerenciando seu sistema com segurança.
              </p>

              <div class="cover-image">
                <img src="assets/images/auth/signin-image.svg" alt="Imagem de acesso ao sistema" />
              </div>
            </div>
          </div>

          <div class="col-lg-6 auth-right">
            <div class="signin-wrapper">
              <div class="form-wrapper">
                <span class="login-badge">
                  <i class="lni lni-lock-alt"></i>
                  Acesso ao sistema
                </span>

                <h2 class="form-title">Entrar no painel</h2>
                <p class="form-subtitle">
                  Informe seu e-mail e sua senha para acessar o
                  <strong>Painel da Distribuidora PLHB</strong>.
                </p>

                <?php if ($loginErro !== ''): ?>
                  <div class="alert-custom alert-danger-custom"><?= e($loginErro) ?></div>
                <?php endif; ?>

                <?php if ($loginOk !== ''): ?>
                  <div class="alert-custom alert-success-custom"><?= e($loginOk) ?></div>
                <?php endif; ?>

                <form action="./assets/auth/processarLogin.php" method="post" autocomplete="on">
                  <?= csrf_input(); ?>
                  <input type="hidden" name="redirect_back" value="../../index.php">

                  <div class="row">
                    <div class="col-12">
                      <div class="input-style-1">
                        <label for="email">E-mail</label>
                        <input
                          type="email"
                          id="email"
                          name="email"
                          value="<?= e($emailValue) ?>"
                          placeholder="Digite seu e-mail"
                          autocomplete="email"
                          required />
                      </div>
                    </div>

                    <div class="col-12">
                      <div class="input-style-1">
                        <label for="senha">Senha</label>
                        <input
                          type="password"
                          id="senha"
                          name="senha"
                          placeholder="Digite sua senha"
                          autocomplete="current-password"
                          required />
                      </div>
                    </div>

                    <div class="col-md-6 mb-25">
                      <div class="form-check checkbox-style">
                        <input
                          class="form-check-input"
                          type="checkbox"
                          value="1"
                          id="lembrar"
                          name="lembrar"
                          <?= $emailCookie !== '' ? 'checked' : '' ?> />
                        <label class="form-check-label" for="lembrar">
                          Lembrar de mim
                        </label>
                      </div>
                    </div>

                    <div class="col-md-6 mb-25 text-md-end">
                      <a href="esqueci-senha.php" class="forgot-link">
                        Esqueceu sua senha?
                      </a>
                    </div>

                    <div class="col-12">
                      <button type="submit" class="main-btn primary-btn btn-hover text-center">
                        Entrar
                      </button>
                    </div>
                  </div>
                </form>

                <div class="login-footer-info">
                  <p>
                    <strong>Painel da Distribuidora PLHB</strong><br />
                    Acesso restrito para usuários autorizados.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <!-- fim lado direito -->
        </div>
      </div>
    </section>
  </main>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    window.addEventListener('load', function() {
      var preloader = document.getElementById('preloader');
      if (preloader) {
        preloader.style.opacity = '0';
        preloader.style.visibility = 'hidden';
        preloader.style.transition = 'all 0.3s ease';
        setTimeout(function() {
          preloader.style.display = 'none';
        }, 300);
      }
    });
  </script>
</body>

</html>