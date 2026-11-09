<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once dirname(__DIR__) . '/frontend/support/helpers.php';

App\Core\Autoloader::register();

use App\Config\ModuleRegistry;

$root = dirname(__DIR__);
$failures = [];
$registry = ModuleRegistry::all();

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(count($registry) === 6, 'somente os seis módulos independentes devem possuir menu no catálogo');

$renderCentralMenu = static function (string $environmentKey, array $environment, string $surface, string $activePage): string {
    $menuEnvironmentKey = $environmentKey;
    $menuVisiblePageKeys = array_keys($environment['pages']);
    $menuSurface = $surface;
    $menuPageKey = $activePage;
    ob_start();
    require dirname(__DIR__) . '/frontend/navigation/module-menu.php';
    return (string) ob_get_clean();
};

foreach ($registry as $environmentKey => $environment) {
    $menuPath = $root . '/' . $environment['menu'];
    $assert(is_file($menuPath), "{$environmentKey}: menu próprio ausente");

    $menuSource = is_file($menuPath) ? (file_get_contents($menuPath) ?: '') : '';
    $assert(str_contains($menuSource, "menuEnvironmentKey = '{$environmentKey}'"), "{$environmentKey}: menu não declara o ambiente correto");

    if ($environmentKey !== 'gestao-acessos') {
        $assert(str_contains($menuSource, 'navigation/module-menu.php'), "{$environmentKey}: menu não usa o renderer central");
    } else {
        $assert(str_contains($menuSource, 'governanca-acessos/usuarios.php'), 'Governança: rota pública de usuários ausente');
        $assert(str_contains($menuSource, 'portal.php'), 'Governança: retorno ao portal ausente');
    }

    foreach ($environment['pages'] as $pageKey => $page) {
        $sidebar = $renderCentralMenu($environmentKey, $environment, 'sidebar', $pageKey);
        $assert(str_contains($sidebar, 'data-menu-environment="' . $environmentKey . '"'), "{$environmentKey}: ambiente ausente no renderer central");
        $assert(substr_count($sidebar, 'class="module-nav-link') === count($environment['pages']), "{$environmentKey}: quantidade de links divergente no renderer central");
        $assert(substr_count($sidebar, 'aria-current="page"') === 1, "{$environmentKey}/{$pageKey}: página ativa inválida");
        $assert(str_contains($sidebar, 'href="portal.php"'), "{$environmentKey}: retorno ao portal ausente");

        foreach ($environment['pages'] as $expectedPage) {
            $assert(str_contains($sidebar, sigas_frontend_escape($expectedPage['label'])), "{$environmentKey}: rótulo {$expectedPage['key']} ausente");
        }
    }

    $mobile = $renderCentralMenu($environmentKey, $environment, 'mobile', (string) $environment['home_page']);
    $mobileCount = count(array_filter($environment['pages'], static fn (array $page): bool => (bool) $page['mobile']));
    $assert(substr_count($mobile, 'class="module-nav-link') === $mobileCount, "{$environmentKey}: navegação móvel divergente");
    $assert(str_contains($mobile, 'data-module-menu-toggle'), "{$environmentKey}: botão Mais ausente");
}

$kitSidebar = $renderCentralMenu('kit-maternidade', $registry['kit-maternidade'], 'sidebar', 'visitas');
$assert(str_contains($kitSidebar, 'kit-maternidade/index.php?pagina=visitas'), 'Kit Maternidade: rota pública de visitas não resolvida');

$aluguelSidebar = $renderCentralMenu('aluguel-social', $registry['aluguel-social'], 'sidebar', 'vistorias');
$assert(str_contains($aluguelSidebar, 'aluguel-social/index.php?pagina=vistorias'), 'Aluguel Social: rota pública de vistorias não resolvida');

$beneficiosSidebar = $renderCentralMenu('beneficios-eventuais', $registry['beneficios-eventuais'], 'sidebar', 'triagem');
$assert(str_contains($beneficiosSidebar, 'beneficios-eventuais/index.php?pagina=triagem'), 'Benefícios Eventuais: rota pública de triagem não resolvida');

$comidaSidebar = $renderCentralMenu('comida-mesa', $registry['comida-mesa'], 'sidebar', 'beneficiarios');
$assert(str_contains($comidaSidebar, 'comida-mesa/beneficiarios.php'), 'Comida na Mesa: rota de beneficiários não resolvida');

$empregoSidebar = $renderCentralMenu('primeiro-emprego', $registry['primeiro-emprego'], 'sidebar', 'candidatos');
$assert(str_contains($empregoSidebar, 'primeiro-emprego/candidatos.php'), 'Primeiro Emprego: rota de candidatos não resolvida');

$comidaMenu = file_get_contents($root . '/frontend/modules/comida-mesa/menu.php') ?: '';
$assert(str_contains($comidaMenu, "cm_can('comida_mesa.cadastrar')"), 'Comida na Mesa: menu deve continuar filtrando cadastro por permissão');
$assert(str_contains($comidaMenu, "cm_can('comida_mesa.entregar')"), 'Comida na Mesa: menu deve continuar filtrando entrega por permissão');

$empregoMenu = file_get_contents($root . '/frontend/modules/primeiro-emprego/menu.php') ?: '';
$assert(str_contains($empregoMenu, 'pe_visible_page_keys()'), 'Primeiro Emprego: menu deve continuar filtrando páginas por permissão');

foreach (['primeiro-emprego', 'comida-mesa'] as $modularEnvironment) {
    $layoutPath = $root . '/' . $modularEnvironment . '/_layout.php';
    $source = is_file($layoutPath) ? (file_get_contents($layoutPath) ?: '') : '';
    $assert($source !== '', "{$modularEnvironment}: layout modular ausente");
    $assert(str_contains($source, 'frontend/layouts/module-layout.php'), "{$modularEnvironment}: layout padrão ausente");
    $assert(str_contains($source, 'ModuleRegistry::findPage'), "{$modularEnvironment}: registry de páginas ausente");
}

foreach (['pessoas.php', 'familias.php', 'atendimentos.php', 'cadastro-anexo.php', 'registro.php'] as $legacyPath) {
    $assert(!is_file($root . '/' . $legacyPath), "página setorial legada não deve existir na raiz: {$legacyPath}");
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
