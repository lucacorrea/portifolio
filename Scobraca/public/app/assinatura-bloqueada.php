<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_login();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assinatura bloqueada - FluxPay</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body class="login-body">
    <main class="login-card">
        <div class="login-brand">FluxPay <span>Empresa</span></div>
        <h1>Acesso temporariamente bloqueado</h1>
        <p>A assinatura da empresa está vencida, bloqueada ou cancelada. Regularize o contrato com o administrador da plataforma para liberar o painel.</p>
        <div class="login-links">
            <a class="btn btn-primary" href="<?= e(public_url('/logout.php')) ?>">Sair com segurança</a>
        </div>
    </main>
</body>
</html>
