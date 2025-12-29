<?php
declare(strict_types=1);
session_start();

$erro  = (string)($_SESSION['flash_erro'] ?? '');
$ok    = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Redefinir senha - SIGRelatórios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link rel="stylesheet" href="./vendors/feather/feather.css">
  <link rel="stylesheet" href="./vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="./vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="./css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="./images/3.png" />

  <style>
    body{ background:#f5f7fb; }
    .auth-card{ border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    .form-control{ height:46px; border-radius:12px; }
    .btn-primary{ border-radius:12px; height:46px; font-weight:700; }
    .brand-logo img{ max-height:48px; }
    .hint{ font-size:12px; opacity:.75; }
  </style>
</head>
<body>
<div class="container-scroller">
  <div class="container-fluid page-body-wrapper full-page-wrapper">
    <div class="content-wrapper d-flex align-items-center auth px-0">
      <div class="row w-100 mx-0">
        <div class="col-lg-4 mx-auto">

          <div class="auth-form-light text-left py-5 px-4 px-sm-5 auth-card">
            <div class="brand-logo text-center mb-3">
              <img src="./images/3.png" alt="SIGRelatórios">
            </div>

            <h4 class="font-weight-bold text-center mb-1">Esqueceu sua senha?</h4>
            <p class="text-muted text-center mb-4">
              Informe seu <b>e-mail</b> (ou <b>nome</b>) para receber um link/código de redefinição.
            </p>

            <?php if ($erro): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
              <div class="alert alert-success"><?= h($ok) ?></div>
            <?php endif; ?>

            <form method="post" action="./controle/auth/enviarRedefinirSenha.php" autocomplete="off">
              <div class="form-group">
                <label class="font-weight-semibold">E-mail ou nome</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="ti-email"></i></span>
                  </div>
                  <input
                    type="text"
                    name="login"
                    class="form-control"
                    placeholder="Digite seu e-mail ou nome"
                    required
                  >
                </div>
                <div class="hint">Se existir uma conta ativa, enviaremos as instruções.</div>
              </div>

              <div class="mt-4">
                <button class="btn btn-primary btn-block">
                  <i class="ti-arrow-right mr-1"></i> Enviar instruções
                </button>
              </div>

              <div class="text-center mt-4 small">
                <a href="./index.php" class="text-muted">Voltar para o login</a>
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
