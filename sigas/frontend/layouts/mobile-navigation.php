<?php declare(strict_types=1); ?>
<nav class="module-mobile-nav frontend-mobile-nav" aria-label="Navegação móvel de <?= sigas_frontend_escape($environment['name']) ?>">
    <?php foreach (array_filter($environment['pages'], static fn (array $item): bool => $item['mobile']) as $navigationPage): ?>
        <a class="module-nav-link<?= $navigationPage['key'] === $pageKey ? ' active' : '' ?>" href="<?= sigas_frontend_escape($navigationPage['href']) ?>"<?= $navigationPage['key'] === $pageKey ? ' aria-current="page"' : '' ?>><i class="bi bi-<?= sigas_frontend_escape($navigationPage['icon']) ?>"></i><span><?= sigas_frontend_escape($navigationPage['label']) ?></span></a>
    <?php endforeach; ?>
    <button type="button" data-frontend-menu-toggle aria-expanded="false" aria-controls="moduleSidebar"><i class="bi bi-three-dots"></i><span>Mais</span></button>
</nav>
