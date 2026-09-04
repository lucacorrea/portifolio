<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;

require_once dirname(__DIR__, 2) . '/support/helpers.php';

$menuEnvironmentKey = 'gestao-acessos';
$menuEnvironment = ModuleRegistry::find($menuEnvironmentKey);

if ($menuEnvironment === null) {
    throw new RuntimeException('O ambiente Governança e Acessos não está registrado.');
}

$publicHrefs = [
    'painel' => 'governanca-acessos/index.php',
    'usuarios' => 'governanca-acessos/usuarios.php',
    'cargos' => 'governanca-acessos/cargos.php',
    'perfis' => 'governanca-acessos/perfis.php',
    'permissoes' => 'governanca-acessos/permissoes.php',
    'setores' => 'governanca-acessos/setores.php',
    'matriz-acesso' => 'governanca-acessos/matriz-acesso.php',
    'auditoria' => 'governanca-acessos/auditoria.php',
    'sessoes' => 'governanca-acessos/sessoes.php',
];

$menuEnvironment['home'] = $publicHrefs['painel'];
$menuPages = $menuEnvironment['pages'];
foreach ($menuPages as $key => &$navigationPage) {
    if (isset($publicHrefs[$key])) {
        $navigationPage['href'] = $publicHrefs[$key];
    }
}
unset($navigationPage);

$environment = $menuEnvironment;
$menuSurface = isset($menuSurface) && is_string($menuSurface) ? $menuSurface : 'sidebar';
$menuPageKey = isset($menuPageKey) && is_string($menuPageKey)
    ? $menuPageKey
    : (isset($pageKey) && is_string($pageKey) ? $pageKey : 'painel');

if ($menuSurface === 'mobile') {
    ?>
    <nav
        class="module-mobile-nav frontend-mobile-nav"
        data-module-menu
        data-menu-environment="gestao-acessos"
        aria-label="Navegação móvel de Governança e Acessos"
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
    data-menu-environment="gestao-acessos"
    aria-label="Menu de Governança e Acessos"
>
    <div class="module-sidebar-head">
        <a class="module-brand" href="<?= sigas_frontend_escape($menuEnvironment['home']) ?>">
            <i class="bi bi-<?= sigas_frontend_escape($menuEnvironment['icon']) ?>"></i>
            <span>
                <small>Módulo SIGAS</small>
                <strong><?= sigas_frontend_escape($menuEnvironment['name']) ?></strong>
            </span>
        </a>
        <button class="btn btn-light btn-icon d-lg-none" type="button" data-module-menu-close aria-label="Fechar menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <nav class="module-nav" aria-label="Navegação de Governança e Acessos">
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
