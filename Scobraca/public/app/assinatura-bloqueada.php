<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_login();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Assinatura bloqueada</title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="login-body">
<main class="login-card">
<h1>Acesso temporariamente bloqueado</h1>
<p>A assinatura da empresa está vencida, bloqueada ou cancelada. Entre em contato com o administrador da plataforma para regularizar o acesso.</p>
<a href="/logout.php" class="btn btn-primary">Sair</a>
</main>
</body>
</html>
