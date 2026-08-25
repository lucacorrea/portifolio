<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Entregas',
    'description' => 'Registro das concessões efetivadas e rastreabilidade do responsável.',
    'actions' => [
    [
        'label' => 'Nova ação',
        'icon' => 'plus-circle',
        'primary' => true
    ]
],
    'stats' => [],
    'filters' => [],
    'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Operação',
        'title' => 'Entregas',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'data',
                'label' => 'Data'
            ],
            [
                'key' => 'pessoa',
                'label' => 'Pessoa'
            ],
            [
                'key' => 'beneficio',
                'label' => 'Benefício'
            ],
            [
                'key' => 'quantidade',
                'label' => 'Qtd./Valor'
            ],
            [
                'key' => 'responsavel',
                'label' => 'Responsável'
            ],
            [
                'key' => 'status',
                'label' => 'Status'
            ]
        ],
        'rows' => [
            [
                'data' => '20/08/2026',
                'pessoa' => 'Pessoa E',
                'beneficio' => 'Cesta emergencial',
                'quantidade' => '1 un.',
                'responsavel' => 'Servidor A',
                'status' => 'Entregue'
            ],
            [
                'data' => '19/08/2026',
                'pessoa' => 'Pessoa F',
                'beneficio' => 'Ajuda humanitária',
                'quantidade' => 'R$ 250,00',
                'responsavel' => 'Servidor B',
                'status' => 'Entregue'
            ]
        ],
        'primary' => 'data'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
