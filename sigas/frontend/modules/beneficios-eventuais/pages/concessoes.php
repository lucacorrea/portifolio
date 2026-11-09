<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Concessões',
    'description' => 'Benefícios deferidos, valores/quantidades e situação da liberação.',
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
        'title' => 'Concessões',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'protocolo',
                'label' => 'Protocolo'
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
                'key' => 'decisao',
                'label' => 'Decisão'
            ],
            [
                'key' => 'status',
                'label' => 'Status'
            ]
        ],
        'rows' => [
            [
                'protocolo' => 'BE-2026-0813',
                'pessoa' => 'Pessoa B',
                'beneficio' => 'Auxílio eventual',
                'quantidade' => 'R$ 300,00',
                'decisao' => 'Deferido',
                'status' => 'Aguardando entrega'
            ],
            [
                'protocolo' => 'BE-2026-0807',
                'pessoa' => 'Pessoa E',
                'beneficio' => 'Cesta emergencial',
                'quantidade' => '1 un.',
                'decisao' => 'Deferido',
                'status' => 'Entregue'
            ]
        ],
        'primary' => 'protocolo'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
