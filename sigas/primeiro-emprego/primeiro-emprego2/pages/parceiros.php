<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Órgãos e instituições parceiras',
    'description' => 'Rede de órgãos públicos, empresas e organizações vinculadas ao programa.',
    'actions' => [['label' => 'Nova instituição', 'icon' => 'building-add', 'primary' => true]],
    'stats' => pe_demo_stats('parceiros'),
    'search_placeholder' => 'Pesquisar instituição, tipo ou responsável',
    'filters' => [
        ['label' => 'Tipo', 'options' => ['Órgão público', 'Empresa privada', 'Organização social']],
        ['label' => 'Situação', 'options' => ['Ativa', 'Pendente']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Rede parceira',
        'title' => 'Instituições cadastradas',
        'primary' => 'instituicao',
        'columns' => [
            ['key' => 'instituicao', 'label' => 'Instituição'], ['key' => 'tipo', 'label' => 'Tipo'], ['key' => 'cnpj', 'label' => 'CNPJ mascarado'],
            ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'telefone', 'label' => 'Telefone'], ['key' => 'email', 'label' => 'E-mail'],
            ['key' => 'oportunidades', 'label' => 'Oportunidades'], ['key' => 'lotados', 'label' => 'Participantes lotados'],
            ['key' => 'parceria', 'label' => 'Parceria'], ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'rows' => pe_demo_partners(),
    ]],
    'modal' => ['title' => 'Detalhes da instituição'],
];
