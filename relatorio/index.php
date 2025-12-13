<?php
declare(strict_types=1);
session_start();

/**
 * Se já estiver logado, manda pro painel certo
 */
if (!empty($_SESSION['usuario_logado'])) {
  $perfis = $_SESSION['perfis'] ?? [];
  if (in_array('ADMIN', $perfis, true)) {
    header('Location: ./painel/adm/index.php');
  } else {
    header('Location: ./painel/operador/index.php');
  }
  exit;
}

/**
 * Flash de erro vindo do controller
 */
$erro = $_SESSION['flash_erro'] ?? '';
unset($_SESSION['flash_erro']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Skydash Admin</title>

  <!-- plugins:css -->
  <link rel="stylesheet" href="./vendors/feather/feather.css">
  <link rel="stylesheet" href="./vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="./vendors/css/vendor.bundle.base.css">
  <!-- endinject -->

  <!-- inject:css -->
  <link rel="stylesheet" href="./css/vertical-layout-light/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="./images/favicon.png" />
</head>

<body>
  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-center auth px-0">
        <div class="row w-100 mx-0">
          <div class="col-lg-4 mx-auto">
            <div class="auth-form-light text-left py-5 px-4 px-sm-5">
              <div class="brand-logo">
                <img src="./images/logo.svg" alt="logo">
              </div>

              <h4>Hello! let's get started</h4>
              <h6 class="font-weight-light">Sign in to continue.</h6>

              <?php if (!empty($erro)): ?>
                <div class="alert alert-danger mt-3" role="alert">
                  <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                </div>
              <?php endif; ?>

              <form class="pt-3" method="POST" action="./controle/auth/login.php">
                <div class="form-group">
                  <input
                    type="email"
                    class="form-control form-control-lg"
                    name="email"
                    placeholder="Email"
                    autocomplete="username"
                    required
                  >
                </div>

                <div class="form-group">
                  <input
                    type="password"
                    class="form-control form-control-lg"
                    name="senha"
                    placeholder="Senha"
                    autocomplete="current-password"
                    required
                  >
                </div>

                <div class="mt-3">
                  <button type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">
                    SIGN IN
                  </button>
                </div>

                <div class="my-2 d-flex justify-content-between align-items-center">
                  <div class="form-check">
                    <label class="form-check-label text-muted">
                      <input type="checkbox" class="form-check-input" name="manter_logado" value="1">
                      Keep me signed in
                    </label>
                  </div>
                  <a href="#" class="auth-link text-black">Forgot password?</a>
                </div>

                <div class="mb-2">
                  <button type="button" class="btn btn-block btn-facebook auth-form-btn" disabled>
                    <i class="ti-facebook mr-2"></i>Connect using facebook
                  </button>
                </div>

                <div class="text-center mt-4 font-weight-light">
                  Don't have an account? <a href="#" class="text-primary">Create</a>
                </div>
              </form>

            </div>
          </div>
        </div>
      </div>
      <!-- content-wrapper ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>

  <!-- plugins:js -->
  <script src="./vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->

  <!-- inject:js -->
  <script src="./js/off-canvas.js"></script>
  <script src="./js/hoverable-collapse.js"></script>
  <script src="./js/template.js"></script>
  <script src="./js/settings.js"></script>
  <script src="./js/todolist.js"></script>
  <!-- endinject -->
</body>
</html>
