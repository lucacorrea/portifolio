<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Capacitações',
    'description' => 'Cursos, oficinas e turmas de qualificação vinculadas ao programa.',
    'actions' => [['label' => 'Nova capacitação', 'icon' => 'mortarboard', 'primary' => true]],
    'stats' => pe_demo_stats('capacitacoes'),
    'search_placeholder' => 'Pesquisar curso, instituição ou turma',
    'filters' => [
        ['label' => 'Situação', 'options' => ['Em andamento', 'Concluída', 'Inscrições abertas']],
        ['label' => 'Certificado', 'options' => ['Previsto', 'Disponível']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Qualificação',
        'title' => 'Capacitações programadas',
        'primary' => 'curso',
        'columns' => [
            ['key' => 'curso', 'label' => 'Curso'], ['key' => 'instituicao', 'label' => 'Instituição'], ['key' => 'turma', 'label' => 'Turma'],
            ['key' => 'carga_horaria', 'label' => 'Carga horária'], ['key' => 'inscritos', 'label' => 'Inscritos'], ['key' => 'concluintes', 'label' => 'Concluintes'],
            ['key' => 'periodo', 'label' => 'Período'], ['key' => 'certificado', 'label' => 'Certificado'], ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'rows' => pe_demo_trainings(),
    ]],
    'modal' => ['title' => 'Detalhes da capacitação'],
];
