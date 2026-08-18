<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Acompanhamentos',
    'description' => 'Registro visual das orientações, avaliações e próximas ações dos participantes.',
    'actions' => [['label' => 'Novo acompanhamento', 'icon' => 'clipboard2-plus', 'primary' => true]],
    'stats' => pe_demo_stats('acompanhamentos'),
    'search_placeholder' => 'Pesquisar participante, instituição ou responsável',
    'filters' => [
        ['label' => 'Tipo', 'options' => ['Contato mensal', 'Orientação', 'Avaliação']],
        ['label' => 'Situação', 'options' => ['Regular', 'Atenção']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Acompanhamento técnico',
        'title' => 'Movimentações recentes',
        'primary' => 'participante',
        'columns' => [
            ['key' => 'participante', 'label' => 'Participante'], ['key' => 'instituicao', 'label' => 'Instituição'], ['key' => 'responsavel', 'label' => 'Responsável'],
            ['key' => 'data', 'label' => 'Data'], ['key' => 'tipo', 'label' => 'Tipo'], ['key' => 'resumo', 'label' => 'Resumo'],
            ['key' => 'proxima_acao', 'label' => 'Próxima ação'], ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'rows' => pe_demo_followups(),
    ]],
    'modal' => ['title' => 'Detalhes do acompanhamento'],
];
