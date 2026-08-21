<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Reavaliações',
    'description' => 'Casos próximos ao fim da concessão para renovar, suspender ou encerrar.',
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
        'title' => 'Reavaliações',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiario',
                'label' => 'Beneficiário'
            ],
            [
                'key' => 'fim',
                'label' => 'Fim atual'
            ],
            [
                'key' => 'ultima_visita',
                'label' => 'Última visita'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ],
            [
                'key' => 'recomendacao',
                'label' => 'Recomendação'
            ],
            [
                'key' => 'prazo',
                'label' => 'Prazo'
            ]
        ],
        'rows' => [
            [
                'beneficiario' => 'Família AS-0148',
                'fim' => '31/08/2026',
                'ultima_visita' => '10/08/2026',
                'situacao' => 'Em reavaliação',
                'recomendacao' => 'Visita complementar',
                'prazo' => '28/08/2026'
            ],
            [
                'beneficiario' => 'Família AS-0139',
                'fim' => '31/08/2026',
                'ultima_visita' => '08/08/2026',
                'situacao' => 'Apta a encerrar',
                'recomendacao' => 'Encerramento',
                'prazo' => '30/08/2026'
            ]
        ],
        'primary' => 'beneficiario'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
