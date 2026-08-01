<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Frequência',
    'description' => 'Acompanhamento demonstrativo de presenças e faltas por competência.',
    'actions' => [['label' => 'Registrar frequência', 'icon' => 'calendar-check', 'primary' => true]],
    'stats' => pe_demo_stats('frequencia'),
    'search_placeholder' => 'Pesquisar participante, instituição ou competência',
    'filters' => [
        ['label' => 'Competência', 'options' => ['Jul/2026']],
        ['label' => 'Situação', 'options' => ['Regular', 'Atenção']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Controle mensal',
        'title' => 'Frequência dos participantes',
        'primary' => 'participante',
        'columns' => [
            ['key' => 'competencia', 'label' => 'Competência'], ['key' => 'participante', 'label' => 'Participante'], ['key' => 'instituicao', 'label' => 'Instituição'],
            ['key' => 'previstos', 'label' => 'Dias previstos'], ['key' => 'presencas', 'label' => 'Presenças'], ['key' => 'faltas', 'label' => 'Faltas'],
            ['key' => 'percentual', 'label' => 'Percentual'], ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'rows' => pe_demo_attendance(),
    ]],
    'modal' => ['title' => 'Detalhes da frequência'],
];
