<?php
declare(strict_types=1);
session_start();

$token = trim((string)($_GET['token'] ?? ''));
$erro  = $_SESSION['flash_erro'] ?? '';
$ok    = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>Nova senha - SIGRelatórios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link rel="stylesheet" href="./vendors/feather/feather.css">
  <link rel="stylesheet" href="./vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="./vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="./css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="./images/3.png" />

  <style>
    body { background: #f5f7fb; }
    .auth-card {
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(0,0,0,.08);
    }
    .form-control {
      height: 46px;
      border-radius: 12px;
    }
    .btn-primary {
      border-radius: 12px;
      height: 46px;
      font-weight: 700;
    }
    .brand-logo img {
      max-height: 48px;
    }
    .hint {
      font-size: 12px;
      opacity: .75;
    }
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

            <h4 class="font-weight-bold text-center mb-1">Criar nova senha</h4>
            <p class="text-muted text-center mb-4">
              Defina uma nova senha segura para sua conta.
            </p>

            <?php if ($erro): ?>
              <div class="alert alert-danger"><?= h($erro) ?></div>
            <?php endif; ?>

            <?php if ($ok): ?>
              <div class="alert alert-success"><?= h($ok) ?></div>
            <?php endif; ?>

            <?php if ($token): ?>
              <form method="post" action="./controle/auth/resetar_senha.php" autocomplete="off">
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <div class="form-group">
                  <label class="font-weight-semibold">Nova senha</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="ti-lock"></i></span>
                    </div>
                    <input
                      type="password"
                      name="senha"
                      class="form-control"
                      minlength="6"
                      placeholder="Digite a nova senha"
                      required
                    >
                  </div>
                  <div class="hint">Mínimo de 6 caracteres</div>
                </div>

                <div class="form-group">
                  <label class="font-weight-semibold">Confirmar senha</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="ti-lock"></i></span>
                    </div>
                    <input
                      type="password"
                      name="senha2"
                      class="form-control"
                      minlength="6"
                      placeholder="Confirme a nova senha"
                      required
                    >
                  </div>
                </div>

                <div class="mt-4">
                  <button class="btn btn-primary btn-block">
                    <i class="ti-check mr-1"></i> Salvar nova senha
                  </button>
                </div>

                <div class="text-center mt-4 small">
                  <a href="./index.php" class="text-muted">
                    Voltar para o login
                  </a>
                </div>
              </form>
            <?php else: ?>
              <div class="alert alert-warning text-center">
                Link inválido ou expirado.
              </div>
              <div class="text-center mt-3">
                <a href="./redefinir-senha.php" class="btn btn-outline-primary btn-sm">
                  Solicitar novo link
                </a>
              </div>
            <?php endif; ?>

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
