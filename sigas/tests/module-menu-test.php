<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once dirname(__DIR__) . '/frontend/support/helpers.php';

App\Core\Autoloader::register();

use App\Config\ModuleRegistry;

$root = dirname(__DIR__);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$renderMenu = static function (string $path, string $surface, string $activePage): string {
    $menuSurface = $surface;
    $menuPageKey = $activePage;
    ob_start();
    require $path;
    return (string) ob_get_clean();
};

foreach (ModuleRegistry::all() as $environmentKey => $environment) {
    $menuPath = $root . '/' . $environment['menu'];

    foreach ($environment['pages'] as $pageKey => $page) {
        $sidebar = $renderMenu($menuPath, 'sidebar', $pageKey);
        $assert(str_contains($sidebar, 'data-menu-environment="' . $environmentKey . '"'), "{$environmentKey}: ambiente ausente no menu");
        $assert(substr_count($sidebar, 'class="module-nav-link') === count($environment['pages']), "{$environmentKey}: quantidade de links divergente");
        $assert(substr_count($sidebar, 'aria-current="page"') === 1, "{$environmentKey}/{$pageKey}: página ativa inválida");
        $assert(str_contains($sidebar, 'Trocar setor ou módulo'), "{$environmentKey}: retorno ao portal ausente");

        foreach ($environment['pages'] as $expectedPage) {
            $assert(str_contains($sidebar, 'href="' . sigas_frontend_escape($expectedPage['href']) . '"'), "{$environmentKey}: link {$expectedPage['key']} ausente");
            $assert(str_contains($sidebar, sigas_frontend_escape($expectedPage['label'])), "{$environmentKey}: rótulo {$expectedPage['key']} ausente");
        }
    }

    $mobile = $renderMenu($menuPath, 'mobile', (string) $environment['home_page']);
    $mobileCount = count(array_filter($environment['pages'], static fn (array $page): bool => (bool) $page['mobile']));
    $assert(substr_count($mobile, 'class="module-nav-link') === $mobileCount, "{$environmentKey}: navegação móvel divergente");
    $assert(str_contains($mobile, 'data-module-menu-toggle'), "{$environmentKey}: botão Mais ausente");
}

$publicFiles = [];

foreach (ModuleRegistry::all() as $environmentKey => $environment) {
    foreach ($environment['pages'] as $page) {
        if ($page['target'] !== 'public') {
            continue;
        }

        $path = parse_url((string) $page['href'], PHP_URL_PATH);

        if (!is_string($path) || str_starts_with($path, 'primeiro-emprego/') || str_starts_with($path, 'comida-mesa/')) {
            continue;
        }

        $publicFiles[$path] = $environmentKey;
    }
}

foreach ($publicFiles as $path => $environmentKey) {
    $source = file_get_contents($root . '/' . $path) ?: '';
    $assert(str_contains($source, "/frontend/modules/{$environmentKey}/menu.php"), "{$path}: menu SSR do ambiente ausente");
    $assert(str_contains($source, '/frontend/layouts/module-topbar.php'), "{$path}: topbar padrão ausente");
    $assert(!str_contains($source, 'id="appSidebar"'), "{$path}: placeholder antigo de sidebar presente");
    $assert(!str_contains($source, 'id="appTopbar"'), "{$path}: placeholder antigo de topbar presente");
    $assert(!str_contains($source, 'id="bottomNavigation"'), "{$path}: placeholder antigo de navegação móvel presente");
}

foreach (['primeiro-emprego', 'comida-mesa'] as $modularEnvironment) {
    $layoutPath = $root . '/' . $modularEnvironment . '/_layout.php';
    $source = is_file($layoutPath) ? (file_get_contents($layoutPath) ?: '') : '';
    $assert($source !== '', "{$modularEnvironment}: layout modular ausente");
    $assert(str_contains($source, 'frontend/layouts/module-layout.php'), "{$modularEnvironment}: layout padrão ausente");
    $assert(str_contains($source, 'ModuleRegistry::findPage'), "{$modularEnvironment}: registry de páginas ausente");
}

foreach (['registro.php', 'cadastro-anexo.php'] as $detailPath) {
    $source = file_get_contents($root . '/' . $detailPath) ?: '';
    $assert(str_contains($source, '/frontend/modules/protecao-social-basica/menu.php'), "{$detailPath}: contexto da Proteção Básica ausente");
}

$navigationScript = file_get_contents($root . '/assets/js/module-navigation.js') ?: '';
$assert(!str_contains($navigationScript, 'innerHTML'), 'module-navigation.js não deve reconstruir o menu');

if ($failures === []) {
    echo 'PASS module-menu-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
