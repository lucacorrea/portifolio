<?php

use FluxEmpresa\Core\Csrf;

defined('APP_PATH') || exit('Acesso direto negado.');
?>
<header class="admin-header">
    <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Abrir menu">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="header-title">
        <span><?= h($layoutContextLabel) ?></span>
        <h1><?= h($pageTitle) ?></h1>
    </div>

    <div class="header-account">
        <div class="account-copy">
            <strong><?= h($layoutUser['nome'] ?? 'Usuário') ?></strong>
            <span><?= h($layoutRoleLabel) ?></span>
        </div>

        <form class="logout-form" method="post" action="logout.php">
            <?= Csrf::field() ?>
            <button class="logout-button" type="submit">Sair</button>
        </form>
    </div>
</header>
