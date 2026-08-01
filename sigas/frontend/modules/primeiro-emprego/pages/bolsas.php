<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Bolsas',
    'description' => 'Visão demonstrativa da conferência de bolsas, sem processamento financeiro real.',
    'actions' => [['label' => 'Conferir competência', 'icon' => 'wallet2', 'primary' => true]],
    'stats' => pe_demo_stats('bolsas'),
    'search_placeholder' => 'Pesquisar participante, instituição ou status',
    'filters' => [
        ['label' => 'Competência', 'options' => ['Jul/2026']],
        ['label' => 'Pagamento', 'options' => ['Em processamento', 'Em análise', 'Programado']],
        ['label' => 'Documentação', 'options' => ['Regular', 'Pendente']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Conferência de bolsas',
        'title' => 'Registros da competência',
        'description' => 'Nenhum pagamento é realizado por esta interface.',
        'primary' => 'participante',
        'columns' => [
            ['key' => 'competencia', 'label' => 'Competência'], ['key' => 'participante', 'label' => 'Participante'], ['key' => 'instituicao', 'label' => 'Instituição'],
            ['key' => 'valor', 'label' => 'Valor'], ['key' => 'frequencia', 'label' => 'Frequência'], ['key' => 'documentacao', 'label' => 'Situação documental'],
            ['key' => 'pagamento', 'label' => 'Status do pagamento'], ['key' => 'data', 'label' => 'Data'],
        ],
        'rows' => pe_demo_grants(),
    ]],
    'modal' => ['title' => 'Detalhes da bolsa'],
];
