<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Vagas e oportunidades',
    'description' => 'Oportunidades demonstrativas e compatibilidade de perfis por instituição.',
    'actions' => [['label' => 'Nova oportunidade', 'icon' => 'briefcase-plus', 'primary' => true]],
    'stats' => pe_demo_stats('vagas'),
    'search_placeholder' => 'Pesquisar cargo, instituição ou requisito',
    'filters' => [
        ['label' => 'Situação', 'options' => ['Aberta', 'Em seleção']],
        ['label' => 'Escolaridade', 'options' => ['Ensino médio', 'Cursando médio']],
        ['label' => 'Carga horária', 'options' => ['20h', '30h']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Oportunidades',
        'title' => 'Vagas disponíveis',
        'primary' => 'cargo',
        'columns' => [
            ['key' => 'cargo', 'label' => 'Cargo'], ['key' => 'instituicao', 'label' => 'Órgão ou instituição'], ['key' => 'setor', 'label' => 'Setor'],
            ['key' => 'quantidade', 'label' => 'Quantidade'], ['key' => 'requisitos', 'label' => 'Requisitos'], ['key' => 'escolaridade', 'label' => 'Escolaridade'],
            ['key' => 'carga_horaria', 'label' => 'Carga horária'], ['key' => 'remuneracao', 'label' => 'Bolsa ou remuneração'],
            ['key' => 'prazo', 'label' => 'Prazo'], ['key' => 'situacao', 'label' => 'Situação'], ['key' => 'compativeis', 'label' => 'Candidatos compatíveis'],
        ],
        'rows' => pe_demo_jobs(),
    ]],
    'modal' => ['title' => 'Detalhes da oportunidade'],
];
