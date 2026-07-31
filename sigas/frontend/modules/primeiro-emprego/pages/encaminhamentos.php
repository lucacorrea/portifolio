<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Encaminhamentos',
    'description' => 'Controle demonstrativo de indicações, retornos e andamento dos candidatos.',
    'actions' => [['label' => 'Novo encaminhamento', 'icon' => 'send', 'primary' => true]],
    'stats' => pe_demo_stats('encaminhamentos'),
    'search_placeholder' => 'Pesquisar candidato, oportunidade ou instituição',
    'filters' => [
        ['label' => 'Situação', 'options' => ['Em andamento', 'Pendente', 'Encaminhado']],
        ['label' => 'Retorno', 'options' => ['Entrevista marcada', 'Aguardando instituição', 'Análise de perfil']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Fluxo de oportunidades',
        'title' => 'Encaminhamentos recentes',
        'primary' => 'candidato',
        'columns' => [
            ['key' => 'candidato', 'label' => 'Candidato'], ['key' => 'oportunidade', 'label' => 'Oportunidade'], ['key' => 'instituicao', 'label' => 'Instituição'],
            ['key' => 'data', 'label' => 'Data'], ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'retorno', 'label' => 'Retorno'],
            ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'rows' => pe_demo_referrals(),
    ]],
    'modal' => ['title' => 'Detalhes do encaminhamento'],
];
