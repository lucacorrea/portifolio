<?php
declare(strict_types=1);
session_start();

$erro  = $_SESSION['flash_erro'] ?? '';
$ok    = $_SESSION['flash_ok'] ?? '';
$email = $_SESSION['redef_email'] ?? '';
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function mask_email(string $email): string {
  $email = trim($email);
  if ($email === '' || strpos($email, '@') === false) return '';
  [$u, $d] = explode('@', $email, 2);
  $u2 = mb_substr($u, 0, 2, 'UTF-8') . str_repeat('*', max(0, mb_strlen($u, 'UTF-8') - 2));
  return $u2 . '@' . $d;
}
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
    .brand-logo img{ max-height:48px; }
    .btn{ border-radius:12px; height:46px; font-weight:700; }
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

            <h4 class="font-weight-bold text-center mb-1">Confira seu e-mail</h4>
            <p class="text-muted text-center mb-4">
              Enviamos instruções para redefinir sua senha<?php if ($email): ?> em <b><?= h(mask_email($email)) ?></b><?php endif; ?>.
            </p>

            <?php if ($erro): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
              <div class="alert alert-success"><?= h($ok) ?></div>
            <?php endif; ?>

            <div class="text-center mt-4">
              <a href="./index.php" class="btn btn-outline-primary btn-block">
                Voltar para o login
              </a>
            </div>

            <div class="text-center mt-3 small text-muted">
              Não recebeu? Verifique spam ou solicite novamente.
              <div class="mt-2">
                <a href="./redefinirSenha.php">Solicitar novamente</a>
              </div>
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
<script src="./js/template.js"></script>
<script src="./js/settings.js"></script>
<script src="./js/todolist.js"></script>
</body>
</html>
