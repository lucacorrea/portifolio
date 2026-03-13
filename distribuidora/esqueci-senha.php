<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/assets/auth/_helpers.php';

$erro = flash_pop('recupera_erro');
$ok   = flash_pop('recupera_ok');

$old = $_SESSION['recupera_old'] ?? [];
unset($_SESSION['recupera_old']);

$emailOld = is_array($old) ? (string)($old['email'] ?? '') : '';
$selfUrl = $_SERVER['PHP_SELF'] ?? 'esqueci-senha.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Esqueci a Senha | Painel da Distribuidora PLHB</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    :root {
      --plhb-primary: #4f46e5;
      --plhb-primary-dark: #4338ca;
      --plhb-soft: #eef2ff;
      --plhb-bg: #f3f4f6;
      --plhb-text: #111827;
      --plhb-muted: #6b7280;
      --plhb-border: #e5e7eb;
      --plhb-white: #ffffff;
      --plhb-danger-bg: #fef2f2;
      --plhb-danger-border: #fecaca;
      --plhb-danger-text: #991b1b;
      --plhb-success-bg: #ecfdf5;
      --plhb-success-border: #a7f3d0;
      --plhb-success-text: #065f46;
    }

    * { box-sizing: border-box; }

    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      background: var(--plhb-bg);
      overflow-x: hidden;
    }

    .main-wrapper {
      width: 100% !important;
      min-height: 100vh;
      margin-left: 0 !important;
      padding: 0 !important;
    }

    .signin-section {
      min-height: 100vh;
      padding: 0;
      display: flex;
      align-items: stretch;
    }

    .signin-section .container-fluid { padding: 0; }
    .auth-row { min-height: 100vh; margin: 0; }

    .auth-left,
    .auth-right {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .auth-left {
      background: linear-gradient(135deg, #eef2ff 0%, #dde7ff 100%);
      position: relative;
      overflow: hidden;
      padding: 40px;
    }

    .auth-left::before {
      content: "";
      position: absolute;
      top: -90px;
      right: -90px;
      width: 320px;
      height: 320px;
      border-radius: 50%;
      background: rgba(79, 70, 229, .08);
    }

    .auth-left::after {
      content: "";
      position: absolute;
      bottom: -110px;
      left: -110px;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(79, 70, 229, .08);
    }

    .auth-cover-content {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 680px;
      text-align: center;
    }

    .auth-cover-content h1 {
      font-size: 2.5rem;
      line-height: 1.2;
      font-weight: 700;
      color: var(--plhb-primary);
      margin-bottom: 14px;
    }

    .auth-cover-content p {
      font-size: 1rem;
      line-height: 1.7;
      color: var(--plhb-muted);
      margin-bottom: 28px;
    }

    .cover-image img {
      width: 100%;
      max-width: 500px;
      height: auto;
      display: block;
      margin: 0 auto;
    }

    .auth-right {
      background: var(--plhb-white);
      padding: 40px;
    }

    .signup-wrapper {
      width: 100%;
      max-width: 540px;
    }

    .badge-access {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      background: var(--plhb-soft);
      border: 1px solid #c7d2fe;
      color: var(--plhb-primary-dark);
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .form-title {
      font-size: 1.9rem;
      font-weight: 700;
      color: var(--plhb-text);
      margin-bottom: 10px;
    }

    .form-subtitle {
      font-size: .98rem;
      line-height: 1.7;
      color: var(--plhb-muted);
      margin-bottom: 28px;
    }

    .input-style-1 { margin-bottom: 18px; }

    .input-style-1 label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      font-weight: 600;
      color: var(--plhb-text);
    }

    .input-style-1 input {
      width: 100%;
      height: 56px;
      border: 1px solid var(--plhb-border);
      background: #f8fafc;
      border-radius: 12px;
      padding: 0 16px;
      font-size: 15px;
      color: var(--plhb-text);
      outline: none;
      transition: all .25s ease;
      box-shadow: none;
    }

    .input-style-1 input:focus {
      background: #fff;
      border-color: var(--plhb-primary);
      box-shadow: 0 0 0 4px rgba(79, 70, 229, .12);
    }

    .main-btn.primary-btn {
      width: 100%;
      height: 56px;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      background: linear-gradient(90deg, var(--plhb-primary) 0%, #5b6df6 100%);
      box-shadow: 0 12px 24px rgba(79, 70, 229, .20);
      transition: all .25s ease;
    }

    .main-btn.primary-btn:hover {
      color: #fff;
      transform: translateY(-1px);
      background: linear-gradient(90deg, var(--plhb-primary-dark) 0%, var(--plhb-primary) 100%);
    }

    .bottom-info {
      margin-top: 26px;
      padding-top: 18px;
      border-top: 1px solid var(--plhb-border);
      text-align: center;
    }

    .bottom-info p {
      margin: 0;
      font-size: 14px;
      line-height: 1.7;
      color: var(--plhb-muted);
    }

    .bottom-info a {
      color: var(--plhb-primary);
      text-decoration: none;
      font-weight: 700;
    }

    .bottom-info a:hover { text-decoration: underline; }

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
  </style>
</head>
<body>
  <main class="main-wrapper">
    <section class="signin-section">
      <div class="container-fluid">
        <div class="row g-0 auth-row">

          <div class="col-lg-6 auth-left">
            <div class="auth-cover-content">
              <h1>Recuperar acesso</h1>
              <p>
                Informe o e-mail cadastrado para receber um codigo de recuperacao.
              </p>
              <div class="cover-image">
                <img src="assets/images/auth/signin-image.svg" alt="Recuperar senha" />
              </div>
            </div>
          </div>

          <div class="col-lg-6 auth-right">
            <div class="signup-wrapper">
              <span class="badge-access">
                <i class="lni lni-envelope"></i>
                Esqueci a senha
              </span>

              <h2 class="form-title">Verificar e-mail</h2>
              <p class="form-subtitle">
                Digite o e-mail da conta para receber um codigo de 6 digitos.
              </p>

              <?php if ($erro !== ''): ?>
                <div class="alert-custom alert-danger-custom"><?= e($erro) ?></div>
              <?php endif; ?>

              <?php if ($ok !== ''): ?>
                <div class="alert-custom alert-success-custom"><?= e($ok) ?></div>
              <?php endif; ?>

              <form action="./assets/auth/processarEsqueciSenha.php" method="post" autocomplete="on">
                <?= csrf_input(); ?>
                <input type="hidden" name="redirect_back" value="<?= e($selfUrl) ?>">

                <div class="input-style-1">
                  <label for="email">E-mail cadastrado</label>
                  <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($emailOld) ?>"
                    placeholder="Digite seu e-mail"
                    autocomplete="email"
                    required />
                </div>

                <div class="button-group d-flex justify-content-center flex-wrap">
                  <button type="submit" class="main-btn primary-btn btn-hover text-center">
                    Enviar codigo
                  </button>
                </div>
              </form>

              <div class="bottom-info">
                <p>
                  Lembrou a senha?
                  <a href="index.php">Voltar para o login</a>
                </p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  </main>
</body>
</html>