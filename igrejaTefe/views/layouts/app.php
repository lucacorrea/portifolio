<?php

use App\Core\Config;
use App\Core\Session;
use App\Core\View;

$appName = Config::get('app.name', 'Igreja Tefe Financeiro');
$userName = Session::get('user_name');
$userRole = Session::get('user_role');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e(($title ?? 'Sistema') . ' | ' . $appName) ?></title>
    <link rel="stylesheet" href="<?= \App\Core\View::e(url('/assets/css/app.css')) ?>">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" data-sidebar>
            <a class="brand" href="<?= \App\Core\View::e(url('/')) ?>">
                <span class="brand-mark">IT</span>
                <span><?= View::e($appName) ?></span>
            </a>

            <nav class="nav-list" aria-label="Navegação principal">
                <a href="<?= \App\Core\View::e(url('/dashboard')) ?>">Dashboard</a>
                <a href="<?= \App\Core\View::e(url('/entradas')) ?>">Entradas</a>
                <a href="<?= \App\Core\View::e(url('/saidas')) ?>">Saídas</a>
                <a href="<?= \App\Core\View::e(url('/categorias')) ?>">Categorias</a>
                <a href="<?= \App\Core\View::e(url('/relatorios')) ?>">Relatórios</a>
                <a href="<?= \App\Core\View::e(url('/configuracoes')) ?>">Configurações</a>
            </nav>

            <?php if ($userName): ?>
                <form class="logout-form" method="post" action="<?= \App\Core\View::e(url('/logout')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= Session::csrfToken() ?>">
                    <button class="button ghost" type="submit">Sair</button>
                </form>
            <?php endif; ?>
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
                    <span>
                        <?php if ($userName): ?>
                            <?= View::e($userName) ?> · <?= View::e($userRole) ?>
                        <?php else: ?>
                            Base multi-igreja com segurança por sessão
                        <?php endif; ?>
                    </span>
                </div>
            </header>

            <?= $content ?>
        </main>
    </div>

    <script src="<?= \App\Core\View::e(url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
