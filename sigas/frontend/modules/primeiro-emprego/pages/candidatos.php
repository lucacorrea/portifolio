<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Candidatos',
    'description' => 'Pesquisa e acompanhamento visual dos perfis inscritos no programa.',
    'actions' => [['label' => 'Novo candidato', 'icon' => 'person-plus', 'primary' => true]],
    'stats' => pe_demo_stats('candidatos'),
    'search_placeholder' => 'Pesquisar candidato, CPF mascarado ou área',
    'filters' => [
        ['label' => 'Situação', 'options' => ['Ativo', 'Encaminhado', 'Aguardando vaga']],
        ['label' => 'Escolaridade', 'options' => ['Ensino médio', 'Superior incompleto']],
        ['label' => 'Disponibilidade', 'options' => ['Integral', 'Manhã', 'Tarde']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Banco de talentos',
        'title' => 'Perfis cadastrados',
        'description' => 'Dados sintéticos e identificadores mascarados.',
        'primary' => 'candidato',
        'columns' => [
            ['key' => 'candidato', 'label' => 'Nome'], ['key' => 'cpf', 'label' => 'CPF'], ['key' => 'idade', 'label' => 'Idade'],
            ['key' => 'telefone', 'label' => 'Telefone'], ['key' => 'escolaridade', 'label' => 'Escolaridade'], ['key' => 'area', 'label' => 'Área de interesse'],
            ['key' => 'disponibilidade', 'label' => 'Disponibilidade'], ['key' => 'situacao', 'label' => 'Situação'], ['key' => 'atualizacao', 'label' => 'Atualização'],
        ],
        'rows' => pe_demo_candidates(),
    ]],
    'modal' => ['title' => 'Detalhes do candidato'],
];
