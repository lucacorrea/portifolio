<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;

require_once dirname(__DIR__) . '/support/helpers.php';

if (!isset($menuEnvironmentKey) || !is_string($menuEnvironmentKey)) {
    throw new RuntimeException('O ambiente do menu não foi informado.');
}

$menuEnvironment = ModuleRegistry::find($menuEnvironmentKey);

if ($menuEnvironment === null) {
    throw new RuntimeException('O ambiente informado não possui menu registrado.');
}

$environment = $menuEnvironment;
$menuSurface = isset($menuSurface) && is_string($menuSurface) ? $menuSurface : 'sidebar';
$menuPageKey = isset($menuPageKey) && is_string($menuPageKey)
    ? $menuPageKey
    : (isset($pageKey) && is_string($pageKey) ? $pageKey : (string) $menuEnvironment['home_page']);
$menuPages = $menuEnvironment['pages'];

/*
 * Filtro visual opcional por módulo.
 *
 * O registro do ModuleRegistry continua sendo a fonte completa de navegação.
 * Cada módulo pode informar $menuVisiblePageKeys para ocultar links que o
 * usuário autenticado não pode utilizar. Isso melhora a UX, mas não substitui
 * a autorização obrigatória no backend de cada página/ação.
 */
if (isset($menuVisiblePageKeys) && is_array($menuVisiblePageKeys)) {
    $visibleKeys = array_fill_keys(array_map('strval', $menuVisiblePageKeys), true);
    $menuPages = array_values(array_filter(
        $menuPages,
        static fn (array $item): bool => isset($visibleKeys[(string) ($item['key'] ?? '')])
    ));
}

if ($menuSurface === 'mobile') {
    ?>
    <nav
        class="module-mobile-nav frontend-mobile-nav"
        data-module-menu
        data-menu-environment="<?= sigas_frontend_escape($menuEnvironmentKey) ?>"
        aria-label="Navegação móvel de <?= sigas_frontend_escape($menuEnvironment['name']) ?>"
    >
        <?php foreach (array_filter($menuPages, static fn (array $item): bool => (bool) $item['mobile']) as $navigationPage): ?>
            <a
                class="module-nav-link<?= $navigationPage['key'] === $menuPageKey ? ' active' : '' ?>"
                href="<?= sigas_frontend_escape($navigationPage['href']) ?>"
                <?= $navigationPage['key'] === $menuPageKey ? 'aria-current="page"' : '' ?>
            >
                <i class="bi bi-<?= sigas_frontend_escape($navigationPage['icon']) ?>"></i>
                <span><?= sigas_frontend_escape($navigationPage['label']) ?></span>
            </a>
        <?php endforeach; ?>
        <button type="button" data-module-menu-toggle aria-expanded="false" aria-controls="moduleSidebar">
            <i class="bi bi-three-dots"></i><span>Mais</span>
        </button>
    </nav>
    <?php
    return;
}

if ($menuSurface !== 'sidebar') {
    throw new RuntimeException('A superfície solicitada para o menu é inválida.');
}
?>
<aside
    class="module-sidebar frontend-sidebar"
    id="moduleSidebar"
    data-module-menu
    data-menu-environment="<?= sigas_frontend_escape($menuEnvironmentKey) ?>"
    aria-label="Menu de <?= sigas_frontend_escape($menuEnvironment['name']) ?>"
>
    <div class="module-sidebar-head">
        <a class="module-brand" href="<?= sigas_frontend_escape($menuEnvironment['home']) ?>">
            <i class="bi bi-<?= sigas_frontend_escape($menuEnvironment['icon']) ?>"></i>
            <span>
                <small><?= $menuEnvironment['kind'] === 'module' ? 'Módulo SIGAS' : 'Setor SEMAS' ?></small>
                <strong><?= sigas_frontend_escape($menuEnvironment['name']) ?></strong>
            </span>
        </a>
        <button class="btn btn-light btn-icon d-lg-none" type="button" data-module-menu-close aria-label="Fechar menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <nav class="module-nav" aria-label="Navegação de <?= sigas_frontend_escape($menuEnvironment['name']) ?>">
        <?php foreach ($menuPages as $navigationPage): ?>
            <a
                class="module-nav-link<?= $navigationPage['key'] === $menuPageKey ? ' active' : '' ?>"
                href="<?= sigas_frontend_escape($navigationPage['href']) ?>"
                <?= $navigationPage['key'] === $menuPageKey ? 'aria-current="page"' : '' ?>
            >
                <i class="bi bi-<?= sigas_frontend_escape($navigationPage['icon']) ?>"></i>
                <span><?= sigas_frontend_escape($navigationPage['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="module-sidebar-footer">
        <a class="module-switch-link" href="portal.php"><i class="bi bi-grid"></i>Trocar setor ou módulo</a>
    </div>
</aside>
