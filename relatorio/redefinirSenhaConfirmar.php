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
    .btn-primary{ border-radius:12px; height:46px; font-weight:700; }
    .brand-logo img{ max-height:48px; }
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

            <h4 class="font-weight-bold text-center mb-2">Verifique seu e-mail</h4>

            <?php if ($erro): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>

            <div class="alert alert-success">
              <?= h($ok ?: 'Se existir uma conta ativa, enviaremos as instruções para o e-mail cadastrado.') ?>
            </div>

            <p class="text-muted small mb-4">
              Procure também na caixa de <b>spam</b> ou <b>lixo eletrônico</b>.
            </p>

            <a href="./redefinirSenha.php" class="btn btn-primary btn-block">
              <i class="ti-reload mr-1"></i> Tentar novamente
            </a>

            <div class="text-center mt-4 small">
              <a href="./index.php" class="text-muted">Voltar para o login</a>
            </div>

          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script src="./vendors/js/vendor.bundle.base.js"></script>
<script src="./js/off-canvas.js"></script>
<script src="./js/hoverable-collapse.js"></script>
<script src="./js/hoverable-collapse.js"></script>
<script src="./js/template.js"></script>
<script src="./js/settings.js"></script>
<script src="./js/todolist.js"></script>
</body>
</html>
