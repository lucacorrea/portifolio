<?php

defined('APP_PATH') || exit('Acesso direto negado.');
?>
<aside class="admin-sidebar" id="admin-sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">FE</div>
        <div>
            <strong>FluxEmpresa</strong>
            <span>MVP Administrativo</span>
        </div>
    </div>

    <div class="sidebar-context <?= $layoutIsSuperAdmin ? 'is-global' : '' ?>">
        <span><?= $layoutIsSuperAdmin ? 'L&J' : 'Empresa' ?></span>
        <strong><?= h($layoutContextLabel) ?></strong>
    </div>

    <nav class="sidebar-nav" aria-label="Menu principal">
        <?php foreach ($layoutMenuItems as $item): ?>
            <?php
                $isActive = $currentPage === $item['key'];
                $isPlaceholder = $item['href'] === '#';
            ?>
            <a
                class="nav-link <?= $isActive ? 'is-active' : '' ?> <?= $isPlaceholder ? 'is-placeholder' : '' ?>"
                href="<?= h($item['href']) ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
                <?= $isPlaceholder ? 'aria-disabled="true"' : '' ?>
            >
                <span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Fechar menu"></button>
