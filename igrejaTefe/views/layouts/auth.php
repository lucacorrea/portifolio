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
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <a class="brand" href="/">
                <span class="brand-mark">IT</span>
                <span><?= View::e($appName) ?></span>
            </a>

            <?= $content ?>
        </section>
    </main>
</body>
</html>

