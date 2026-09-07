<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function opf_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$expectedPages = [
    'kit-maternidade' => [
        'painel', 'beneficiarias', 'cadastro', 'visitas', 'reunioes',
        'avaliacao', 'entregas', 'pos-parto', 'relatorios',
    ],
    'aluguel-social' => [
        'painel', 'beneficiarios', 'solicitacoes', 'vistorias', 'pareceres',
        'concessoes', 'pagamentos', 'reavaliacoes', 'relatorios',
    ],
    'beneficios-eventuais' => [
        'painel', 'solicitacoes', 'triagem', 'analises', 'concessoes',
        'entregas', 'tipos', 'relatorios',
    ],
];

$access = file_get_contents($root . '/frontend/support/operational-program-access.php') ?: '';
$menu = file_get_contents($root . '/frontend/navigation/module-menu.php') ?: '';
$header = file_get_contents($root . '/frontend/components/page-header.php') ?: '';
$js = file_get_contents($root . '/assets/js/modules/operational-programs.js') ?: '';
$css = file_get_contents($root . '/assets/css/modules/operational-programs.css') ?: '';

opf_assert($access !== '', 'helper de autorização operacional deve existir');
opf_assert($js !== '', 'JavaScript compartilhado dos fronts deve existir');
opf_assert($css !== '', 'CSS compartilhado dos fronts deve existir');
opf_assert(str_contains($access, 'sigas_operational_can_page'), 'helper deve validar permissão por página');
opf_assert(str_contains($access, 'sigas_operational_visible_pages'), 'helper deve filtrar menu por permissão');
opf_assert(str_contains($access, 'sigas_operational_filter_page_definition'), 'helper deve filtrar ações de cabeçalho');
opf_assert(str_contains($js, 'operationalProgramAccess'), 'JavaScript deve receber snapshot de acesso do backend');
opf_assert(str_contains($js, 'moduleHomes'), 'JavaScript deve manter navegação dentro da pasta do módulo');
opf_assert(str_contains($js, 'schemas'), 'JavaScript deve possuir formulários contextuais de front');
opf_assert(str_contains($menu, "'kit-maternidade' => 'kit-maternidade/index.php'"), 'menu deve usar pasta pública do Kit Maternidade');
opf_assert(str_contains($menu, "'aluguel-social' => 'aluguel-social/index.php'"), 'menu deve usar pasta pública do Aluguel Social');
opf_assert(str_contains($menu, "'beneficios-eventuais' => 'beneficios-eventuais/index.php'"), 'menu deve usar pasta pública de Benefícios Eventuais');
opf_assert(str_contains($header, "'kit-maternidade' => 'kit-maternidade/index.php'"), 'breadcrumb deve manter Kit Maternidade na pasta pública');

foreach ($expectedPages as $module => $pages) {
    $layoutPath = $root . '/' . $module . '/_layout.php';
    $layout = is_file($layoutPath) ? (file_get_contents($layoutPath) ?: '') : '';

    opf_assert($layout !== '', $module . ' deve possuir _layout.php');
    opf_assert(str_contains($layout, 'operational-program-access.php'), $module . ' deve carregar autorização compartilhada');
    opf_assert(str_contains($layout, 'sigas_operational_can_page'), $module . ' deve bloquear página interna sem permissão');
    opf_assert(str_contains($layout, 'sigas_operational_visible_pages'), $module . ' deve filtrar navegação interna');
    opf_assert(str_contains($layout, 'operational-programs.css'), $module . ' deve carregar CSS operacional');
    opf_assert(str_contains($layout, 'operational-programs.js'), $module . ' deve carregar JS operacional');

    foreach ($pages as $page) {
        $viewPath = $root . '/frontend/modules/' . $module . '/pages/' . $page . '.php';
        opf_assert(is_file($viewPath), $module . '/' . $page . ' deve possuir view frontal');
        opf_assert(str_contains($access, "'" . $page . "' =>"), $module . '/' . $page . ' deve possuir regra de permissão');
    }
}

if ($failures === []) {
    echo 'PASS operational-program-front-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
