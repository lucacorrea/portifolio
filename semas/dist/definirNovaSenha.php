<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/assets/conexao.php';
date_default_timezone_set('America/Manaus');

// E-mail pela query ou sessão
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($email === '' && !empty($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
}
?>
<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Definir Nova Senha - Coari Meu Lar</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <!-- Library / Plugin Css Build -->
    <link rel="stylesheet" href="./assets/css/core/libs.min.css">

    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="./assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Custom Css -->
    <link rel="stylesheet" href="./assets/css/custom.min.css?v=4.0.0">

    <!-- Dark Css -->
    <link rel="stylesheet" href="./assets/css/dark.min.css">

    <!-- Customizer Css -->
    <link rel="stylesheet" href="./assets/css/customizer.min.css">

    <!-- RTL Css -->
    <link rel="stylesheet" href="./assets/css/rtl.min.css">
</head>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
    <!-- loader Start -->
    <div id="loading">
        <div class="loader simple-loader">
            <div class="loader-body"></div>
        </div>
    </div>
    <!-- loader END -->

    <div class="wrapper">
        <section class="login-content">
            <div class="row m-0 align-items-center bg-white">
                <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
                    <img src="./assets/images/auth/02.png" class="img-fluid gradient-main animated-scaleX" alt="images">
                </div>
                <div class="col-md-6 p-0">
                    <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
                        <div class="card-body">
                            <a href="#" class="navbar-brand d-flex align-items-center mb-3">
                                <div class="logo-main" style="margin: 0 auto !important;">
                                    <div class="logo-normal">
                                        <img src="./assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                             style="width:250px;height:240px;" />
                                    </div>
                                    <div class="logo-mini">
                                        <img src="./assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                             style="width:20px;height:20px;" />
                                    </div>
                                </div>
                            </a>

                            <h2 class="mb-2" style="margin-top: -60px;">Definir Nova Senha</h2>
                            <p>Defina sua nova senha para a conta <strong><?php echo htmlspecialchars($email ?: '—'); ?></strong>.</p>

                            <form action="./auth/definirNovaSenhaPost.php" method="POST" id="formNovaSenha">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="floating-label form-group mb-3">
                                            <label for="senha" class="form-label">Nova Senha</label>
                                            <input type="password" class="form-control" id="senha" name="senha" placeholder=" " required>
                                            <small class="text-muted d-block mt-1">
                                                Mínimo <strong>8 caracteres</strong>. Ex.: <code>usuario123</code>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="floating-label form-group mb-3">
                                            <label for="senha2" class="form-label">Confirmar Senha</label>
                                            <input type="password" class="form-control" id="senha2" name="senha2" placeholder=" " required>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary col-12 col-md-12">Salvar Nova Senha</button>
                            </form>

                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <!-- JS libs -->
    <script src="./assets/js/core/libs.min.js"></script>
    <script src="./assets/js/core/external.min.js"></script>
    <script src="./assets/js/hope-ui.js" defer></script>

    <script>
      // Validação simples no front: apenas mínimo de 8 caracteres e confirmação igual
      document.getElementById('formNovaSenha').addEventListener('submit', function(e){
        const s1 = document.getElementById('senha').value;
        const s2 = document.getElementById('senha2').value;

        if (s1 !== s2) {
          e.preventDefault();
          alert('As senhas não conferem.');
          return;
        }
        if (s1.length < 8) {
          e.preventDefault();
          alert('A senha precisa ter pelo menos 8 caracteres.');
        }
      });
    </script>
</body>
</html>
