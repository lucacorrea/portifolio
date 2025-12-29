<?php
declare(strict_types=1);
session_start();

/**
 * NÃO REDIRECIONAR AUTOMATICAMENTE SE JÁ ESTIVER LOGADO
 * (a tela sempre aparece)
 */

/* Flash */
$erro = (string)($_SESSION['flash_erro'] ?? '');
$ok   = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Login</title>

  <link rel="stylesheet" href="./vendors/feather/feather.css">
  <link rel="stylesheet" href="./vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="./vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="./css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="./images/3.png" />

  <style>
    .auth-form-light{ border-radius:16px; }
    .brand-logo img{ max-height:46px; }
    .form-control.form-control-lg{ border-radius:12px; height:46px; }
    .auth-form-btn{ border-radius:12px; }
    .login-title{ margin-bottom:4px; font-weight:800; }
    .login-sub{ margin-bottom:0; opacity:.85; }
  </style>
</head>

<body>
  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-center auth px-0">
        <div class="row w-100 mx-0">
          <div class="col-lg-4 mx-auto">

            <div class="auth-form-light text-left py-5 px-4 px-sm-5">
              

              <h4 class="login-title">Acessar o SIGRelatórios</h4>
              <h6 class="font-weight-light login-sub">Entre com seu e-mail e senha para continuar.</h6>

              <?php if ($ok !== ''): ?>
                <div class="alert alert-success mt-3 mb-0" role="alert"><?= h($ok) ?></div>
              <?php endif; ?>

              <?php if ($erro !== ''): ?>
                <div class="alert alert-danger mt-3 mb-0" role="alert"><?= h($erro) ?></div>
              <?php endif; ?>

              <form class="pt-4" method="POST" action="./controle/auth/login.php" autocomplete="off">
                <div class="form-group">
                  <label class="mb-1 text-muted">E-mail</label>
                  <input type="email" class="form-control form-control-lg" name="email"
                         placeholder="Digite seu e-mail" autocomplete="username" required>
                </div>

                <div class="form-group">
                  <label class="mb-1 text-muted">Senha</label>
                  <input type="password" class="form-control form-control-lg" name="senha"
                         placeholder="Digite sua senha" autocomplete="current-password" required>
                </div>

                <div class="mt-3">
                  <button type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">
                    Entrar
                  </button>
                </div>

                <div class="my-3 d-flex justify-content-between align-items-center">
                  <div class="form-check m-0">
                    <label class="form-check-label text-muted">
                      <input type="checkbox" class="form-check-input" name="manter_logado" value="1">
                      Manter conectado
                    </label>
                  </div>

                  <a href="#" class="auth-link text-black" title="Implemente recuperação se quiser">
                    Esqueci minha senha
                  </a>
                </div>

                <div class="text-center mt-4 small text-muted">
                  Acesso restrito • Perfis: <b>Gestor Geral</b> e <b>Administrador</b>
                </div>
              </form>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="./vendors/js/vendor.bundle.base.js"></script>
  <script src="./js/off-canvas.js"></script>
  <script src="./js/hoverable-collapse.js"></script>
  <script src="./js/template.js"></script>
  <script src="./js/settings.js"></script>
  <script src="./js/todolist.js"></script>
</body>
</html>
