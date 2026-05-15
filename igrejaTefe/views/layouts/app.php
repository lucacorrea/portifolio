<?php

use App\Core\Config;
use App\Core\View;

$appName = Config::get('app.name', 'Igreja Tefe Financeiro');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e(($title ?? 'Sistema') . ' | ' . $appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" data-sidebar>
            <a class="brand" href="/">
                <span class="brand-mark">IT</span>
                <span><?= View::e($appName) ?></span>
            </a>

            <nav class="nav-list" aria-label="Navegação principal">
                <a href="/dashboard">Dashboard</a>
                <a href="/entradas">Entradas</a>
                <a href="/saidas">Saídas</a>
                <a href="/categorias">Categorias</a>
                <a href="/relatorios">Relatórios</a>
                <a href="/configuracoes">Configurações</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <button class="icon-button" type="button" data-sidebar-toggle aria-label="Abrir menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div>
                    <strong><?= View::e($title ?? 'Sistema') ?></strong>
                    <span>Base multi-igreja com segurança por sessão</span>
                </div>
            </header>

            <?= $content ?>
        </main>
    </div>

    <script src="/assets/js/app.js" defer></script>
</body>
</html>

