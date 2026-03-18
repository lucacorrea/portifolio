<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - ANEXO</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="./dist/assets/images/logo/logo_pmc_2025.jpg">

    <!-- Library / Plugin Css Build -->
    <link rel="stylesheet" href="./dist/assets/css/core/libs.min.css">


    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="./dist/assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Custom Css -->
    <link rel="stylesheet" href="./dist/assets/css/custom.min.css?v=4.0.0">

    <!-- Dark Css -->
    <link rel="stylesheet" href="./dist/assets/css/css/dark.min.css">

    <link rel="stylesheet" href="./dist/assets/css/hope-ui.css">

    <!-- Customizer Css -->
    <link rel="stylesheet" href="./dist/assets/css/customizer.min.css">

    <!-- RTL Css -->
    <link rel="stylesheet" href="./dist/assets/css/rtl.min.css">


</head>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">

    <div class="wrapper">
        <section class="login-content">
            <div class="row m-0 align-items-center bg-white">
                <div class="col-md-6">
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="card card-transparent shadow-none d-flex justify-content-center mb-0 auth-card">
                                <div class="card-body">
                                    <a href="#" class="navbar-brand d-flex align-items-center mb-3">

                                        <!--Logo start-->
                                        <div class="logo-main" style="margin: 0 auto !important;">
                                            <div class="logo-normal">
                                                <img src="./dist/assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                                    style="width:250px;height:240px;" />
                                            </div>
                                            <div class="logo-mini">
                                                <img src="./dist/assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                                    style="width:20px;height:20px;" />
                                            </div>
                                        </div>
                                        <!--logo End-->

                                    </a>
                                    <p class="text-center" style="margin-top: -60px;">Faça login para acessar o painel Admnistrativo.</p>
                                    <form action="./dist/auth/processarLogin.php" method="POST" novalidate>
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label for="login" class="form-label">E-mail ou CPF</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        id="login"
                                                        name="login"
                                                        placeholder="Digite seu e-mail ou CPF"
                                                        required
                                                        autocomplete="username"
                                                        inputmode="email">
                                                    <small id="loginHelp" class="form-text text-muted"></small>
                                                </div>
                                            </div>

                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label for="password" class="form-label">Senha</label>
                                                    <input
                                                        type="password"
                                                        class="form-control"
                                                        id="password"
                                                        name="password"
                                                        placeholder="Digite sua senha"
                                                        required
                                                        minlength="6"
                                                        autocomplete="current-password">
                                                </div>
                                            </div>

                                            <div class="col-lg-12 d-flex justify-content-between mt-2 mb-3">
                                                <a href="./dist/recuperarSenha.php">Esqueceu a senha?</a>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-center">
                                            <button type="submit" class="btn btn-primary col-md-12 col-12">Entrar</button>
                                        </div>

                                        <p class="mt-3 text-center">
                                            Não tem uma conta? <a href="criarConta.php" class="text-underline">Clique aqui para se cadastrar.</a>
                                        </p>
                                    </form>

                                    <script>
                                        (function() {
                                            const loginInput = document.getElementById('login');
                                            const help = document.getElementById('loginHelp');

                                            // Detecção simples
                                            const isDigitsOnly = (v) => /^\d+$/.test(v);
                                            const isEmailLike = (v) => v.includes('@');

                                            // Validação básica de email (HTML5 já valida, mas reforçamos)
                                            const isValidEmail = (v) => {
                                                // remove espaços e normaliza rápido
                                                v = v.trim();
                                                // regex simples e efetiva (minúsculas/maiúsculas)
                                                return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
                                            };

                                            // Máscara CPF 000.000.000-00
                                            function formatCPF(str) {
                                                const nums = str.replace(/\D/g, '').slice(0, 11);
                                                let out = nums;
                                                if (nums.length > 3) out = nums.slice(0, 3) + '.' + nums.slice(3);
                                                if (nums.length > 6) out = out.slice(0, 7) + '.' + out.slice(7);
                                                if (nums.length > 9) out = out.slice(0, 11) + '-' + out.slice(11);
                                                return out;
                                            }

                                            // Atualiza UI e validação conforme digita
                                            loginInput.addEventListener('input', () => {
                                                let v = loginInput.value.trim();

                                                // Se só dígitos => formata como CPF
                                                if (isDigitsOnly(v.replace(/\D/g, ''))) {
                                                    loginInput.value = formatCPF(v);
                                                    loginInput.setCustomValidity(''); // válido como CPF
                                                    help.textContent = 'Digite seu CPF ou troque para e-mail.';
                                                    return;
                                                }

                                                // Se parece e-mail, valida
                                                if (isEmailLike(v)) {
                                                    if (!isValidEmail(v)) {
                                                        loginInput.setCustomValidity('Digite um e-mail válido (ex: exemplo@dominio.com)');
                                                        help.textContent = 'E-mail inválido.';
                                                    } else {
                                                        loginInput.setCustomValidity('');
                                                        help.textContent = '';
                                                    }
                                                } else {
                                                    // Nem CPF (só dígitos) nem e-mail -> limpar mensagens
                                                    loginInput.setCustomValidity('');
                                                    help.textContent = 'Digite um e-mail válido ou um CPF (somente números).';
                                                }
                                            });

                                            // No submit, se for e-mail inválido, impede envio e mostra mensagem nativa
                                            loginInput.form.addEventListener('submit', (e) => {
                                                const v = loginInput.value.trim();
                                                const onlyDigits = v.replace(/\D/g, '');

                                                if (isEmailLike(v) && !isValidEmail(v)) {
                                                    // força o navegador a exibir o tooltip de erro
                                                    loginInput.reportValidity();
                                                    e.preventDefault();
                                                    return;
                                                }

                                                // Se for CPF, mantemos com máscara (o back-end já limpa com only_digits)
                                                // Se quiser enviar sem máscara, descomente abaixo:
                                                // if (isDigitsOnly(onlyDigits)) {
                                                //   loginInput.value = onlyDigits;
                                                // }
                                            });
                                        })();
                                    </script>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
                    <img src="./dist/assets/images/auth/01.png" class="img-fluid gradient-main animated-scaleX" alt="images">
                </div>
            </div>
        </section>
    </div>

    <!-- Library Bundle Script -->
    <script src="./dist/assets/js/core/libs.min.js"></script>

    <!-- External Library Bundle Script -->
    <script src="./dist/assets/js/core/external.min.js"></script>

    <!-- Widgetchart Script -->
    <script src="./dist/assets/js/charts/widgetcharts.js"></script>

    <!-- mapchart Script -->
    <script src="./dist/assets/js/charts/vectore-chart.js"></script>
    <script src="./dist/assets/js/charts/dashboard.js"></script>

    <!-- fslightbox Script -->
    <script src="./dist/assets/js/plugins/fslightbox.js"></script>

    <!-- Settings Script -->
    <script src="./dist/assets/js/plugins/setting.js"></script>


    <!-- Form Wizard Script -->
    <script src="./dist/assets/js/plugins/form-wizard.js"></script>

    <!-- AOS Animation Plugin-->

    <!-- App Script -->
    <script src="./dist/assets/js/hope-ui.js" defer></script>


</body>

</html>