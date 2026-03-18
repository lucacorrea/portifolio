<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Recuperar Senha - Coari Meu Lar</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <!-- Library / Plugin Css Build -->
    <link rel="stylesheet" href="../dist/assets/css/core/libs.min.css">


    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="../dist/assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Custom Css -->
    <link rel="stylesheet" href="../dist/assets/css/custom.min.css?v=4.0.0">

    <!-- Dark Css -->
    <link rel="stylesheet" href="../dist/assets/css/dark.min.css">

    <!-- Customizer Css -->
    <link rel="stylesheet" href="../dist/assets/css/customizer.min.css">

    <!-- RTL Css -->
    <link rel="stylesheet" href="../dist/assets/css/rtl.min.css">


</head>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
    <!-- loader Start -->
    <div id="loading">
        <div class="loader simple-loader">
            <div class="loader-body">
            </div>
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

                                <!--Logo start-->
                                <div class="logo-main" style="margin: 0 auto !important;">
                                    <div class="logo-normal">
                                        <img src="../dist/assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                            style="width:250px;height:240px;" />
                                    </div>
                                    <div class="logo-mini">
                                        <img src="../dist/assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                            style="width:20px;height:20px;" />
                                    </div>
                                </div>
                                <!--logo End-->

                            </a>
                            <h2 class="mb-2" style="margin-top: -60px;">Redefinir Senha</h2>
                            <p>Digite seu endereço de e-mail e enviaremos um e-mail com instruções para redefinir sua senha.</p>
                            <form action="./auth/verificarEmail.php" method="POST">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="floating-label form-group">
                                            <label for="email" class="form-label">E-mail</label>
                                            <input type="email" class="form-control" id="email" name="email" aria-describedby="email" placeholder=" ">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary col-12 col-md-12">Redefinir</button>
                            </form>

                            <div class="text-center mt-4">
                                <a href="./index.php" class="back-link">
                                    <span>&larr; Voltar</span>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <!-- Library Bundle Script -->
    <script src="../dist/assets/js/core/libs.min.js"></script>

    <!-- External Library Bundle Script -->
    <script src="../dist/assets/js/core/external.min.js"></script>

    <!-- Widgetchart Script -->
    <script src="../dist/assets/js/charts/widgetcharts.js"></script>

    <!-- mapchart Script -->
    <script src="../dist/assets/js/charts/vectore-chart.js"></script>
    <script src="../dist/assets/js/charts/dashboard.js"></script>

    <!-- fslightbox Script -->
    <script src="../dist/assets/js/plugins/fslightbox.js"></script>

    <!-- Settings Script -->
    <script src="../dist/assets/js/plugins/setting.js"></script>

    <!-- Slider-tab Script -->
    <script src="../dist/assets/js/plugins/slider-tabs.js"></script>

    <!-- Form Wizard Script -->
    <script src="../dist/assets/js/plugins/form-wizard.js"></script>

    <!-- AOS Animation Plugin-->

    <!-- App Script -->
    <script src="../dist/assets/js/hope-ui.js" defer></script>


</body>

</html>