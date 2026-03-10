<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Criar Conta | Painel da Distribuidora PLHB</title>

  <!-- ========== CSS ========= -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    :root {
      --plhb-primary: #4f46e5;
      --plhb-primary-dark: #4338ca;
      --plhb-soft: #eef2ff;
      --plhb-bg: #f3f4f6;
      --plhb-text: #111827;
      --plhb-muted: #6b7280;
      --plhb-border: #e5e7eb;
      --plhb-white: #ffffff;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      background: var(--plhb-bg);
      overflow-x: hidden;
    }

    .main-wrapper {
      width: 100% !important;
      min-height: 100vh;
      margin-left: 0 !important;
      padding: 0 !important;
    }

    .signin-section {
      min-height: 100vh;
      padding: 0;
      display: flex;
      align-items: stretch;
    }

    .signin-section .container-fluid {
      padding: 0;
    }

    .auth-row {
      min-height: 100vh;
      margin: 0;
    }

    .auth-left,
    .auth-right {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .auth-left {
      background: linear-gradient(135deg, #eef2ff 0%, #dde7ff 100%);
      position: relative;
      overflow: hidden;
      padding: 40px;
    }

    .auth-left::before {
      content: "";
      position: absolute;
      top: -90px;
      right: -90px;
      width: 320px;
      height: 320px;
      border-radius: 50%;
      background: rgba(79, 70, 229, .08);
    }

    .auth-left::after {
      content: "";
      position: absolute;
      bottom: -110px;
      left: -110px;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(79, 70, 229, .08);
    }

    .auth-cover-content {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 680px;
      text-align: center;
    }

    .auth-cover-content h1 {
      font-size: 2.5rem;
      line-height: 1.2;
      font-weight: 700;
      color: var(--plhb-primary);
      margin-bottom: 14px;
    }

    .auth-cover-content p {
      font-size: 1rem;
      line-height: 1.7;
      color: var(--plhb-muted);
      margin-bottom: 28px;
    }

    .cover-image img {
      width: 100%;
      max-width: 500px;
      height: auto;
      display: block;
      margin: 0 auto;
    }

    .auth-right {
      background: var(--plhb-white);
      padding: 40px;
    }

    .signup-wrapper {
      width: 100%;
      max-width: 540px;
    }

    .form-wrapper {
      width: 100%;
      border-radius: 18px;
    }

    .badge-access {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      background: var(--plhb-soft);
      border: 1px solid #c7d2fe;
      color: var(--plhb-primary-dark);
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .form-title {
      font-size: 1.9rem;
      font-weight: 700;
      color: var(--plhb-text);
      margin-bottom: 10px;
    }

    .form-subtitle {
      font-size: .98rem;
      line-height: 1.7;
      color: var(--plhb-muted);
      margin-bottom: 28px;
    }

    .input-style-1 {
      margin-bottom: 18px;
    }

    .input-style-1 label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      font-weight: 600;
      color: var(--plhb-text);
    }

    .input-style-1 input {
      width: 100%;
      height: 56px;
      border: 1px solid var(--plhb-border);
      background: #f8fafc;
      border-radius: 12px;
      padding: 0 16px;
      font-size: 15px;
      color: var(--plhb-text);
      outline: none;
      transition: all .25s ease;
      box-shadow: none;
    }

    .input-style-1 input:focus {
      background: #fff;
      border-color: var(--plhb-primary);
      box-shadow: 0 0 0 4px rgba(79, 70, 229, .12);
    }

    .checkbox-style {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 24px;
    }

    .checkbox-style .form-check-input {
      width: 18px;
      height: 18px;
      margin-top: 2px;
      cursor: pointer;
      border-radius: 4px;
    }

    .checkbox-style .form-check-label {
      font-size: 14px;
      line-height: 1.5;
      color: var(--plhb-muted);
      cursor: pointer;
    }

    .checkbox-style .form-check-label a {
      color: var(--plhb-primary);
      text-decoration: none;
      font-weight: 600;
    }

    .checkbox-style .form-check-label a:hover {
      text-decoration: underline;
    }

    .main-btn.primary-btn {
      width: 100%;
      height: 56px;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      background: linear-gradient(90deg, var(--plhb-primary) 0%, #5b6df6 100%);
      box-shadow: 0 12px 24px rgba(79, 70, 229, .20);
      transition: all .25s ease;
    }

    .main-btn.primary-btn:hover {
      color: #fff;
      transform: translateY(-1px);
      background: linear-gradient(90deg, var(--plhb-primary-dark) 0%, var(--plhb-primary) 100%);
    }

    .bottom-info {
      margin-top: 26px;
      padding-top: 18px;
      border-top: 1px solid var(--plhb-border);
      text-align: center;
    }

    .bottom-info p {
      margin: 0;
      font-size: 14px;
      line-height: 1.7;
      color: var(--plhb-muted);
    }

    .bottom-info a {
      color: var(--plhb-primary);
      text-decoration: none;
      font-weight: 700;
    }

    .bottom-info a:hover {
      text-decoration: underline;
    }

    #preloader {
      position: fixed;
      inset: 0;
      z-index: 99999;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    #preloader .spinner {
      width: 42px;
      height: 42px;
      border: 4px solid #e5e7eb;
      border-top-color: var(--plhb-primary);
      border-radius: 50%;
      animation: spin .8s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    @media (max-width: 991.98px) {
      .auth-left {
        display: none;
      }

      .auth-right {
        min-height: 100vh;
        padding: 30px 20px;
      }

      .signup-wrapper {
        max-width: 100%;
      }

      .form-title {
        font-size: 1.6rem;
      }
    }

    @media (max-width: 575.98px) {
      .auth-right {
        padding: 24px 16px;
      }

      .input-style-1 input,
      .main-btn.primary-btn {
        height: 52px;
      }
    }
  </style>
</head>

<body>
  <!-- ======== Preloader =========== -->
  <div id="preloader">
    <div class="spinner"></div>
  </div>

  <!-- ======== main-wrapper start =========== -->
  <main class="main-wrapper">
    <section class="signin-section">
      <div class="container-fluid">
        <div class="row g-0 auth-row">

          <!-- LADO ESQUERDO -->
          <div class="col-lg-6 auth-left">
            <div class="auth-cover-content">
              <h1>Crie sua conta</h1>
              <p>
                Cadastre-se para acessar o
                <strong>Painel da Distribuidora PLHB</strong>
                e começar a utilizar o sistema com mais praticidade, controle e segurança.
              </p>

              <div class="cover-image">
                <img src="assets/images/auth/signin-image.svg" alt="Imagem de cadastro no sistema" />
              </div>
            </div>
          </div>

          <!-- LADO DIREITO -->
          <div class="col-lg-6 auth-right">
            <div class="signup-wrapper">
              <div class="form-wrapper">
                <span class="badge-access">
                  <i class="lni lni-user"></i>
                  Novo cadastro
                </span>

                <h2 class="form-title">Criar conta</h2>
                <p class="form-subtitle">
                  Preencha os dados abaixo para criar seu acesso ao
                  <strong>Painel da Distribuidora PLHB</strong>.
                </p>

                <form action="signup.php" method="post" autocomplete="on">
                  <div class="row">
                    <div class="col-12">
                      <div class="input-style-1">
                        <label for="nome">Nome completo</label>
                        <input
                          type="text"
                          id="nome"
                          name="nome"
                          placeholder="Digite seu nome completo"
                          required />
                      </div>
                    </div>

                    <div class="col-12">
                      <div class="input-style-1">
                        <label for="email">E-mail</label>
                        <input
                          type="email"
                          id="email"
                          name="email"
                          placeholder="Digite seu e-mail"
                          autocomplete="email"
                          required />
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="input-style-1">
                        <label for="senha">Senha</label>
                        <input
                          type="password"
                          id="senha"
                          name="senha"
                          placeholder="Digite sua senha"
                          autocomplete="new-password"
                          required />
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="input-style-1">
                        <label for="confirmar_senha">Confirmar senha</label>
                        <input
                          type="password"
                          id="confirmar_senha"
                          name="confirmar_senha"
                          placeholder="Confirme sua senha"
                          autocomplete="new-password"
                          required />
                      </div>
                    </div>

                    <div class="col-12">
                      <div class="form-check checkbox-style">
                        <input
                          class="form-check-input"
                          type="checkbox"
                          value="1"
                          id="aceite"
                          name="aceite"
                          required />
                        <label class="form-check-label" for="aceite">
                          Li e concordo com os
                          <a href="#">termos de uso</a>
                          e com a
                          <a href="#">política de privacidade</a>.
                        </label>
                      </div>
                    </div>

                    <div class="col-12">
                      <div class="button-group d-flex justify-content-center flex-wrap">
                        <button type="submit" class="main-btn primary-btn btn-hover text-center">
                          Criar conta
                        </button>
                      </div>
                    </div>
                  </div>
                </form>

                <div class="bottom-info">
                  <p>
                    Já possui uma conta?
                    <a href="signin.html">Entrar no sistema</a>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <!-- fim col -->

        </div>
      </div>
    </section>
  </main>
  <!-- ======== main-wrapper end =========== -->

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    window.addEventListener('load', function() {
      var preloader = document.getElementById('preloader');
      if (preloader) {
        preloader.style.opacity = '0';
        preloader.style.visibility = 'hidden';
        preloader.style.transition = 'all 0.3s ease';
        setTimeout(function() {
          preloader.style.display = 'none';
        }, 300);
      }
    });
  </script>
</body>

</html>