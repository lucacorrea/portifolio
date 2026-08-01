<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Lotações',
    'description' => 'Acompanhamento visual da vinculação de participantes às instituições parceiras.',
    'actions' => [['label' => 'Nova lotação', 'icon' => 'diagram-3', 'primary' => true]],
    'stats' => pe_demo_stats('lotacoes'),
    'search_placeholder' => 'Pesquisar participante, instituição ou função',
    'filters' => [
        ['label' => 'Situação', 'options' => ['Ativa', 'Em adaptação']],
        ['label' => 'Jornada', 'options' => ['20h', '30h']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Alocação',
        'title' => 'Participantes lotados',
        'primary' => 'participante',
        'columns' => [
            ['key' => 'participante', 'label' => 'Participante'], ['key' => 'instituicao', 'label' => 'Instituição'], ['key' => 'setor', 'label' => 'Setor'],
            ['key' => 'funcao', 'label' => 'Função'], ['key' => 'inicio', 'label' => 'Início'], ['key' => 'jornada', 'label' => 'Jornada'],
            ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'rows' => pe_demo_placements(),
    ]],
    'modal' => ['title' => 'Detalhes da lotação'],
];
