<?php
use App\Services\MenuService;
?>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <button class="sidebar-toggle" id="sidebarToggle">☰</button>

        <div>
            <div class="brand-title">Meu SaaS</div>
            <div class="brand-subtitle">Painel</div>
        </div>
    </div>

    <nav class="sidebar-nav">

        <?php foreach ($menuItems as $item): ?>

            <?php if (!empty($item['children'])): ?>

                <div class="sidebar-group">
                    <div class="sidebar-group-title">
                        <?= $item['label'] ?>
                    </div>

                    <?php foreach ($item['children'] as $child): ?>
                        <a href="<?= $child['route'] ?>"
                           class="nav-link <?= MenuService::isActive($activeMenu, $child['key']) ? 'is-active' : '' ?>">
                            <?= $child['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <a href="<?= $item['route'] ?>"
                   class="nav-link <?= MenuService::isActive($activeMenu, $item['key']) ? 'is-active' : '' ?>">
                    <?= $item['label'] ?>
                </a>

            <?php endif; ?>

        <?php endforeach; ?>

    </nav>

</aside>