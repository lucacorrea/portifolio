<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Análises e pareceres',
    'description' => 'Avaliação dos critérios, registro técnico e recomendação.',
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
        'title' => 'Análises e pareceres',
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
                'key' => 'tipo',
                'label' => 'Tipo'
            ],
            [
                'key' => 'responsavel',
                'label' => 'Responsável'
            ],
            [
                'key' => 'parecer',
                'label' => 'Parecer'
            ],
            [
                'key' => 'decisao',
                'label' => 'Decisão'
            ]
        ],
        'rows' => [
            [
                'protocolo' => 'BE-2026-0814',
                'pessoa' => 'Pessoa A',
                'tipo' => 'Cesta emergencial',
                'responsavel' => 'Técnica A',
                'parecer' => 'Favorável',
                'decisao' => 'Deferir'
            ],
            [
                'protocolo' => 'BE-2026-0809',
                'pessoa' => 'Pessoa D',
                'tipo' => 'Ajuda humanitária',
                'responsavel' => 'Técnica B',
                'parecer' => 'Pendência',
                'decisao' => 'Aguardar'
            ]
        ],
        'primary' => 'protocolo'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
