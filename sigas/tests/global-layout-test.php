<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$globalCss = $root . '/assets/css/sigas-global-layout.css';
$legacyCss = $root . '/assets/css/sigas-legacy-layout.css';
$head = file_get_contents($root . '/frontend/layouts/head.php') ?: '';
$pageHeader = file_get_contents($root . '/frontend/components/page-header.php') ?: '';
$stats = file_get_contents($root . '/frontend/components/stat-grid.php') ?: '';
$filters = file_get_contents($root . '/frontend/components/filter-bar.php') ?: '';
$table = file_get_contents($root . '/frontend/components/data-table.php') ?: '';
$navigation = file_get_contents($root . '/assets/js/module-navigation.js') ?: '';
$comidaUi = file_get_contents($root . '/frontend/modules/comida-mesa/lib/list-ui.php') ?: '';

$assert(is_file($globalCss), 'Design System Global não encontrado.');
$assert(is_file($legacyCss), 'Ponte visual para páginas legadas não encontrada.');
$assert(str_contains($head, 'assets/css/sigas-global-layout.css'), 'head.php deve carregar o Design System Global.');
$assert(str_contains($pageHeader, 'sigas-page-header'), 'Cabeçalho compartilhado deve usar sigas-page-header.');
$assert(str_contains($stats, 'sigas-kpi-grid') && str_contains($stats, 'sigas-kpi'), 'Indicadores compartilhados devem usar classes globais.');
$assert(str_contains($filters, 'sigas-filter-panel'), 'Filtro compartilhado deve usar sigas-filter-panel.');
$assert(str_contains($table, 'sigas-table-card') && str_contains($table, 'sigas-pagination'), 'Tabela compartilhada deve usar classes globais.');
$assert(str_contains($comidaUi, 'sigas-workspace-hero') && str_contains($comidaUi, 'sigas-kpi-grid'), 'Comida na Mesa deve estar conectado aos componentes globais.');
$assert(str_contains($navigation, 'sigas-legacy-module-page'), 'Navegação deve conectar páginas legadas ao layout global.');
$assert(str_contains($navigation, 'sigas-legacy-layout.css'), 'Navegação deve carregar a ponte visual nas páginas legadas.');
$assert(!str_contains($navigation, 'innerHTML'), 'module-navigation.js não deve reconstruir o menu com innerHTML.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS global-layout-test' . PHP_EOL;
