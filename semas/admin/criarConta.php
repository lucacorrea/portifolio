<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Criar Conta - Coari Meu Lar</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <!-- Library / Plugin Css Build -->
    <link rel="stylesheet" href="../dist/assets/css/core/libs.min.css">


    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="./dist/assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Custom Css -->
    <link rel="stylesheet" href="../dist/assets/css/custom.min.css?v=4.0.0">

    <!-- Dark Css -->
    <link rel="stylesheet" href="../dist/assets/css/css/dark.min.css">

    <link rel="stylesheet" href="../dist/assets/css/hope-ui.css">

    <!-- Customizer Css -->
    <link rel="stylesheet" href="../dist/assets/css/customizer.min.css">

    <!-- RTL Css -->
    <link rel="stylesheet" href="../dist/assets/css/rtl.min.css">


</head>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">

    <div class="wrapper">
        <section class="login-content">
            <div class="row m-0 align-items-center bg-white">
                <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
                    <img src="../dist/assets/images/auth/05.png" class="img-fluid gradient-main animated-scaleX" alt="images">
                </div>
                <div class="col-md-6">
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
                                <div class="card-body">
                                    <a href="../index.php" class="navbar-brand d-flex align-items-center mb-3">

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
                                    <p class="text-center" style="margin-top: -60px;">Crie sua conta.</p>
                                    <form action="./auth/processarCadastro.php" method="POST">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label for="name" class="form-label">Nome Completo</label>
                                                    <input type="text" class="form-control" id="name" name="name" placeholder="Digite seu nome completo" required>
                                                </div>
                                            </div>

                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label for="email" class="form-label">E-mail</label>
                                                    <input type="email" class="form-control" id="email" name="email"
                                                        placeholder="Digite seu e-mail"
                                                        required
                                                        pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                                        title="Digite um e-mail válido. Ex: exemplo@dominio.com">
                                                </div>
                                            </div>

                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label for="cpf" class="form-label">CPF</label>
                                                    <input type="text" class="form-control" id="cpf" name="cpf"
                                                        placeholder="Digite seu CPF" maxlength="14"
                                                        oninput="formatCPF(this)" required>
                                                </div>
                                            </div>

                                            <script>
                                                function formatCPF(input) {
                                                    let value = input.value.replace(/\D/g, '');
                                                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                                                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                                                    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                                                    input.value = value;
                                                }
                                            </script>

                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="password" class="form-label">Senha</label>
                                                    <input type="password" class="form-control" id="password" name="password" placeholder="" required minlength="6">
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="confirm-password" class="form-label">Confirmar Senha</label>
                                                    <input type="password" class="form-control" id="confirm-password" name="confirm_password" placeholder="" required minlength="6">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-center mt-2 mb-3">
                                            <button type="submit" class="btn btn-primary col-12 col-md-12">Criar Conta</button>
                                        </div>

                                        <p class="mt-3 text-center col-12 col-md-12">
                                            Já tem uma conta? <a href="./index.php" class="text-underline">Entrar</a>
                                        </p>
                                    </form>

                                </div>
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
    <script src=".../dist/assets/js/plugins/setting.js"></script>

    <!-- Slider-tab Script -->
    <script src="../dist/assets/js/plugins/slider-tabs.js"></script>

    <!-- Form Wizard Script -->
    <script src="../dist/assets/js/plugins/form-wizard.js"></script>

    <!-- AOS Animation Plugin-->

    <!-- App Script -->
    <script src="../dist/assets/js/hope-ui.js" defer></script>


</body>

</html>