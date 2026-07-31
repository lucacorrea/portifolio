<?php declare(strict_types=1); ?>
<aside class="module-sidebar frontend-sidebar" id="moduleSidebar" aria-label="Menu de <?= sigas_frontend_escape($environment['name']) ?>">
    <div class="module-sidebar-head">
        <a class="module-brand" href="<?= sigas_frontend_escape($environment['home']) ?>">
            <i class="bi bi-<?= sigas_frontend_escape($environment['icon']) ?>"></i>
            <span><small><?= $environment['kind'] === 'module' ? 'Módulo SIGAS' : 'Setor SEMAS' ?></small><strong><?= sigas_frontend_escape($environment['name']) ?></strong></span>
        </a>
        <button class="btn btn-light btn-icon d-lg-none" type="button" data-frontend-menu-close aria-label="Fechar menu"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="module-nav" aria-label="Navegação de <?= sigas_frontend_escape($environment['name']) ?>">
        <?php foreach ($environment['pages'] as $navigationPage): ?>
            <a class="module-nav-link<?= $navigationPage['key'] === $pageKey ? ' active' : '' ?>" href="<?= sigas_frontend_escape($navigationPage['href']) ?>"<?= $navigationPage['key'] === $pageKey ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-<?= sigas_frontend_escape($navigationPage['icon']) ?>"></i><span><?= sigas_frontend_escape($navigationPage['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="module-sidebar-footer"><a class="module-switch-link" href="portal.php"><i class="bi bi-grid"></i>Trocar setor ou módulo</a></div>
</aside>
