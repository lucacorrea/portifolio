<?php

require_once __DIR__ . '/../app/bootstrap.php';

use FluxEmpresa\Core\Auth;

Auth::requireLogin();

$user = Auth::user();
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | FluxEmpresa</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <header class="topbar">
        <strong>FluxEmpresa</strong>
        <span><?= h($user['nome'] ?? 'Usuário') ?> • <?= h($user['perfil'] ?? '') ?></span>
    </header>

    <main class="container">
        <section class="hero-panel">
            <h1>Dashboard do MVP</h1>
            <p>Base criada para o Codex implementar os módulos de empresas, clientes, produtos/serviços, solicitações, orçamentos, execução, pagamentos e relatórios.</p>
        </section>

        <?php if (Auth::isSuperAdmin()): ?>
            <section class="card-grid">
                <article class="card">
                    <h2>Área Super Admin L&J</h2>
                    <p>Esta área terá acesso global a todas as empresas, sem precisar usar o login de cada cliente.</p>
                </article>
                <article class="card">
                    <h2>Trocar contexto de empresa</h2>
                    <p>O próximo passo é criar o seletor seguro de empresa ativa para suporte, auditoria e administração.</p>
                </article>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
