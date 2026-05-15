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
    <title><?= View::e(($title ?? 'Acesso') . ' | ' . $appName) ?></title>
    <link rel="stylesheet" href="<?= \App\Core\View::e(url('/assets/css/app.css')) ?>">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <a class="brand" href="<?= \App\Core\View::e(url('/')) ?>">
                <span class="brand-mark">SF</span>
                <span>Gestão Financeira</span>
            </a>

            <?= $content ?>
        </section>
    </main>
</body>
</html>
