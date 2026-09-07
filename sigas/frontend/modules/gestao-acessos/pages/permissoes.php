<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceService $governance */
$governance = require dirname(__DIR__) . '/bootstrap.php';
$data = $governance->permissionsPage();

$operationalModules = [
    'Kit Maternidade',
    'Aluguel Social',
    'Benefícios Eventuais',
    'Governança e Acessos',
    'Coari Comida na Mesa',
    'Coari Meu Primeiro Emprego',
];
$modulePosition = array_flip($operationalModules);
$moduleRows = [];
$internalRows = [];

foreach ($data['rows'] as $row) {
    $moduleLabel = (string) ($row['modulo'] ?? '');

    if (array_key_exists($moduleLabel, $modulePosition)) {
        $moduleRows[] = $row;
    } else {
        $internalRows[] = $row;
    }
}

usort(
    $moduleRows,
    static function (array $a, array $b) use ($modulePosition): int {
        $moduleA = (string) ($a['modulo'] ?? '');
        $moduleB = (string) ($b['modulo'] ?? '');
        $positionComparison = ($modulePosition[$moduleA] ?? PHP_INT_MAX) <=> ($modulePosition[$moduleB] ?? PHP_INT_MAX);

        if ($positionComparison !== 0) {
            return $positionComparison;
        }

        return strcasecmp((string) ($a['permissao'] ?? ''), (string) ($b['permissao'] ?? ''));
    }
);

$activeModulePermissions = count(array_filter(
    $moduleRows,
    static fn (array $row): bool => ($row['situacao'] ?? null) === 'Ativa'
));

return sigas_frontend_page([
    'title' => 'Permissões por módulo',
    'description' => 'Catálogo de ações autorizáveis separado pelos seis módulos operacionais do SIGAS.',
    'actions' => [
        [
            'label' => 'Níveis de usuário',
            'icon' => 'person-gear',
            'href' => 'governanca-acessos/perfis.php',
        ],
        [
            'label' => 'Matriz de acesso',
            'icon' => 'grid-3x3-gap',
            'primary' => true,
            'href' => 'governanca-acessos/matriz-acesso.php',
        ],
    ],
    'stats' => [
        [
            'label' => 'Módulos operacionais',
            'value' => '6',
            'detail' => 'Estrutura principal do portal',
            'icon' => 'grid',
        ],
        [
            'label' => 'Permissões dos módulos',
            'value' => (string) count($moduleRows),
            'detail' => 'Ações cadastradas nos seis módulos',
            'icon' => 'key',
        ],
        [
            'label' => 'Permissões ativas',
            'value' => (string) $activeModulePermissions,
            'detail' => 'Disponíveis para vinculação',
            'icon' => 'shield-check',
        ],
        [
            'label' => 'Internas/legadas',
            'value' => (string) count($internalRows),
            'detail' => 'Preservadas fora da matriz principal',
            'icon' => 'archive',
        ],
    ],
    'filters' => [
        ['label' => 'Módulo', 'options' => $operationalModules],
        ['label' => 'Situação', 'options' => ['Ativa', 'Inativa']],
    ],
    'search_placeholder' => 'Pesquisar permissão, slug, módulo ou nível',
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Módulos operacionais',
            'title' => 'Permissões dos seis módulos',
            'description' => 'Cada permissão pertence a um módulo específico e pode ser vinculada aos níveis por meio de nivel_permissoes.',
            'columns' => [
                ['key' => 'permissao', 'label' => 'Permissão'],
                ['key' => 'slug', 'label' => 'Slug'],
                ['key' => 'modulo', 'label' => 'Módulo'],
                ['key' => 'niveis', 'label' => 'Níveis'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $moduleRows,
            'primary' => 'permissao',
        ],
        [
            'type' => 'table',
            'kicker' => 'Compatibilidade',
            'title' => 'Permissões internas e legadas preservadas',
            'description' => 'Estas permissões continuam existentes para não quebrar funções históricas do SIGAS, mas não são misturadas à matriz dos seis módulos operacionais.',
            'columns' => [
                ['key' => 'permissao', 'label' => 'Permissão'],
                ['key' => 'slug', 'label' => 'Slug'],
                ['key' => 'modulo', 'label' => 'Área interna'],
                ['key' => 'niveis', 'label' => 'Níveis'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $internalRows,
            'primary' => 'permissao',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
