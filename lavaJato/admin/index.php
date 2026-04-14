<!doctype html>
<html lang="pt" dir="ltr">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
   <title>admin - Auto Peças</title>

   <!-- Favicon / Icon -->
   <link rel="icon" type="image/x-icon" href="../public/assets/images/dashboard/icon.png">
   <link rel="shortcut icon" href="../public/assets/images/favicon.ico">

   <!-- Library / Plugin Css Build -->
   <link rel="stylesheet" href="../public/assets/css/core/libs.min.css">

   <!-- Hope Ui Design System Css -->
   <link rel="stylesheet" href="../public/assets/css/hope-ui.min.css?v=4.0.0">
   <link rel="stylesheet" href="../public/assets/css/hope-ui.css">

   <!-- Custom Css -->
   <link rel="stylesheet" href="../public/assets/css/custom.min.css?v=4.0.0">

   <!-- Dark Css -->
   <link rel="stylesheet" href="../public/assets/css/dark.min.css">

   <!-- Customizer Css -->
   <link rel="stylesheet" href="../public/assets/css/customizer.min.css">
   <link rel="stylesheet" href="../public/assets/css/customizer.css">

   <!-- RTL Css -->
   <link rel="stylesheet" href="../public/assets/css/rtl.min.css">
</head>

<style>
   @media (max-width: 767.98px) {
      .logo-icon {
         display: block !important;
         width: 4rem !important;
         height: auto !important;
         margin: -5rem 0 0 -2rem !important;
      }
      .logo-title {
         display: block !important;
         font-size: 1rem !important;
         margin: -6rem 0 0 -1rem !important;
         color: #000 !important;
      }
   }
</style>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
   <?php
   // mensagem de erro via GET
   $mensagemErro = '';
   if (isset($_GET['erro'])) {
      if ($_GET['erro'] == '1') {
         $mensagemErro = 'Usuário ou senha inválidos.';
      } elseif ($_GET['erro'] == '2') {
         $mensagemErro = 'Erro no sistema: ' . htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');
      }
   }
   ?>
   <div class="wrapper">
      <section class="login-content">
         <div class="row m-0 align-items-center bg-white vh-100">
            <div class="col-md-6">
               <div class="row justify-content-center">
                  <div class="col-md-10">
                     <div class="card card-transparent shadow-none d-flex justify-content-center mb-0 auth-card">
                        <div class="card-body">
                           <a href="#" class="navbar-brand d-flex align-items-center mb-3">
                              <div class="logo-main">
                                 <div class="logo-normal">
                                    <img src="../public/assets/images/auth/ode.png" class="logo-icon"
                                       alt="logo" style="width: 8rem; height: 8rem; margin-left: -10rem; margin-top: -11rem;">
                                 </div>
                              </div>
                              <h4 class="logo-title" style="margin-left: -4rem; margin-top: -11.5rem;">AutoERP</h4>
                           </a>

                           <h2 class="mb-2 text-center">Entrar</h2>
                           <p class="text-center">Faça login para permanecer conectado.</p>

                           <?php if ($mensagemErro): ?>
                              <div class="alert alert-danger text-center"><?= $mensagemErro ?></div>
                           <?php endif; ?>

                           <form action="./actions/processarLogin.php" method="POST" autocomplete="off">
                              <div class="row">
                                 <div class="col-lg-12">
                                    <div class="form-group">
                                       <label for="usuario" class="form-label">E-mail, CPF ou Nome</label>
                                       <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Digite seu e-mail, CPF ou Nome" required>
                                    </div>
                                 </div>
                                 <div class="col-lg-12">
                                    <div class="form-group">
                                       <label for="senha" class="form-label">Senha</label>
                                       <input type="password" class="form-control" id="senha" name="senha" placeholder="Digite sua senha" required>
                                    </div>
                                 </div>
                                 <div class="col-lg-12 d-flex justify-content-between mb-4">
                                    <a href="../confirmaEmail.php">Esqueceu a senha?</a>
                                 </div>
                              </div>
                              <div class="d-flex justify-content-center">
                                 <button type="submit" class="btn btn-primary">Entrar</button>
                              </div>
                           </form>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
               <img src="../public/assets/images/auth/03.png" class="img-fluid gradient-main animated-scaleX" alt="images">
            </div>
         </div>
      </section>
   </div>

   <!-- Scripts -->
   <script src="../public/assets/js/core/libs.min.js"></script>
   <script src="../public/assets/js/core/external.min.js"></script>
   <script src="../public/assets/js/charts/widgetcharts.js"></script>
   <script src="../public/assets/js/charts/vectore-chart.js"></script>
   <script src="../public/assets/js/charts/dashboard.js"></script>
   <script src="../public/assets/js/plugins/fslightbox.js"></script>
   <script src="../public/assets/js/plugins/setting.js"></script>
   <script src="../public/assets/js/plugins/slider-tabs.js"></script>
   <script src="../public/assets/js/plugins/form-wizard.js"></script>
   <script src="../public/assets/js/hope-ui.js" defer></script>
</body>
</html>
