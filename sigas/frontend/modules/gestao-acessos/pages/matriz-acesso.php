<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceService $governance */
$governance = require dirname(__DIR__) . '/bootstrap.php';
$matrixData = $governance->accessMatrixPage();
$permissionsData = $governance->permissionsPage();

$moduleCatalog = [
    'modulo_kit_maternidade' => [
        'label' => 'Kit Maternidade',
        'permission' => 'kit_maternidade.visualizar',
        'scope' => 'Setor + nível + exceção individual',
    ],
    'modulo_aluguel_social' => [
        'label' => 'Aluguel Social',
        'permission' => 'aluguel_social.visualizar',
        'scope' => 'Setor + nível + exceção individual',
    ],
    'modulo_beneficios_eventuais' => [
        'label' => 'Benefícios Eventuais',
        'permission' => 'beneficios_eventuais.visualizar',
        'scope' => 'Setor + nível + exceção individual',
    ],
    'modulo_governanca' => [
        'label' => 'Governança e Acessos',
        'permission' => 'Administrador ou Suporte',
        'scope' => 'Escopo global restrito',
    ],
    'modulo_comida_mesa' => [
        'label' => 'Coari Comida na Mesa',
        'permission' => 'comida_mesa.visualizar',
        'scope' => 'Setor + nível + exceção individual',
    ],
    'modulo_primeiro_emprego' => [
        'label' => 'Coari Meu Primeiro Emprego',
        'permission' => 'primeiro_emprego.visualizar',
        'scope' => 'Setor + nível + exceção individual',
    ],
];

$permissionTotals = array_fill_keys(array_column($moduleCatalog, 'label'), 0);
$activePermissionTotals = $permissionTotals;

foreach ($permissionsData['rows'] as $permissionRow) {
    $moduleLabel = (string) ($permissionRow['modulo'] ?? '');

    if (!array_key_exists($moduleLabel, $permissionTotals)) {
        continue;
    }

    $permissionTotals[$moduleLabel]++;

    if (($permissionRow['situacao'] ?? null) === 'Ativa') {
        $activePermissionTotals[$moduleLabel]++;
    }
}

$moduleRows = [];
foreach ($moduleCatalog as $columnKey => $module) {
    $label = $module['label'];
    $activeCount = (int) ($activePermissionTotals[$label] ?? 0);
    $totalCount = (int) ($permissionTotals[$label] ?? 0);

    $moduleRows[] = [
        'modulo' => $label,
        'permissoes' => $activeCount . ' ativa(s) de ' . $totalCount,
        'entrada' => $module['permission'],
        'regra' => $module['scope'],
        'situacao' => $totalCount > 0 ? 'Configurado' : 'Sem catálogo',
    ];
}

$columns = [
    ['key' => 'nivel', 'label' => 'Nível'],
    ['key' => 'escopo', 'label' => 'Escopo'],
];

foreach ($moduleCatalog as $columnKey => $module) {
    $columns[] = ['key' => $columnKey, 'label' => $module['label']];
}

$rows = [];
foreach ($matrixData['rows'] as $sourceRow) {
    $row = [
        'nivel' => (string) ($sourceRow['nivel'] ?? 'Nível'),
        'escopo' => (string) ($sourceRow['escopo'] ?? 'Setor'),
    ];

    foreach ($moduleCatalog as $columnKey => $module) {
        $value = (string) ($sourceRow[$columnKey] ?? 'Não configurado');
        $activeTotal = (int) ($activePermissionTotals[$module['label']] ?? 0);

        if (preg_match('/^Liberado\s*·\s*(\d+)$/u', $value, $matches) === 1 && $activeTotal > 0) {
            $value = 'Liberado · ' . (int) $matches[1] . '/' . $activeTotal;
        }

        $row[$columnKey] = $value;
    }

    $rows[] = $row;
}

$totalActiveModulePermissions = array_sum($activePermissionTotals);

return sigas_frontend_page([
    'title' => 'Matriz de acesso por módulo',
    'description' => 'Visão consolidada das permissões dos seis módulos operacionais do SIGAS, separadas das áreas internas e legadas.',
    'actions' => [
        [
            'label' => 'Níveis de usuário',
            'icon' => 'person-gear',
            'href' => 'governanca-acessos/perfis.php',
        ],
        [
            'label' => 'Permissões por módulo',
            'icon' => 'key',
            'primary' => true,
            'href' => 'governanca-acessos/permissoes.php',
        ],
    ],
    'stats' => [
        [
            'label' => 'Níveis avaliados',
            'value' => (string) count($rows),
            'detail' => 'Somente níveis ativos',
            'icon' => 'person-gear',
        ],
        [
            'label' => 'Módulos operacionais',
            'value' => '6',
            'detail' => 'Separados na matriz principal',
            'icon' => 'grid-3x3-gap',
        ],
        [
            'label' => 'Permissões ativas',
            'value' => (string) $totalActiveModulePermissions,
            'detail' => 'Somente dos seis módulos',
            'icon' => 'key',
        ],
        [
            'label' => 'Regra de acesso',
            'value' => 'Combinada',
            'detail' => 'Permissão + setor + exceção auditável',
            'icon' => 'shield-lock',
        ],
    ],
    'filters' => [],
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Estrutura de autorização',
            'title' => 'Seis módulos protegidos',
            'description' => 'A autorização do módulo é independente: liberar o setor não concede acesso se o nível não possuir a permissão mínima correspondente.',
            'columns' => [
                ['key' => 'modulo', 'label' => 'Módulo'],
                ['key' => 'permissoes', 'label' => 'Permissões'],
                ['key' => 'entrada', 'label' => 'Permissão mínima'],
                ['key' => 'regra', 'label' => 'Regra'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $moduleRows,
            'primary' => 'modulo',
        ],
        [
            'type' => 'table',
            'kicker' => 'Níveis × módulos',
            'title' => 'Matriz principal de acesso',
            'description' => '“Liberado · X/Y” indica quantas das Y permissões ativas daquele módulo estão concedidas ao nível. Áreas internas e legadas não aparecem nesta matriz.',
            'columns' => $columns,
            'rows' => $rows,
            'primary' => 'nivel',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
