<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Controle Juridico</title>
    <link rel="stylesheet" href="assets/css/app.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-panel">
            <div class="login-brand">
                <span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span>
                <div>
                    <strong>SCP Pessoal</strong>
                    <small>Controle Juridico</small>
                </div>
            </div>
            <h1>Acesso ao Sistema</h1>
            <p>Entre com seu usuario para acompanhar prazos, pagamentos e processos pessoais.</p>

            <form id="login-form" class="login-form">
                <div class="alert-box error" id="login-error" hidden></div>
                <label>
                    <span>Usuario</span>
                    <input type="text" id="login" autocomplete="username" required autofocus>
                </label>
                <label>
                    <span>Senha</span>
                    <div class="password-field">
                        <input type="password" id="senha" autocomplete="current-password" required>
                        <button type="button" class="icon-button" data-toggle-password="senha" title="Mostrar senha" aria-label="Mostrar senha">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </label>
                <button class="btn primary block" type="submit" id="login-submit">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Entrar
                </button>
            </form>

         
        </section>

        <section class="login-aside" aria-hidden="true">
            <div class="login-metric">
                <span>SCP</span>
                <strong>Controle profissional de processos pessoais</strong>
            </div>
        </section>
    </main>
    <div id="toast-root" class="toast-root" aria-live="polite"></div>
    <script src="assets/js/app.js?v=2"></script>
</body>
</html>
