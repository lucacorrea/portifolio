<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Entregas de kits',
    'description' => 'Controle de kits aprovados, reserva, lote, entrega, termo e responsável.',
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
        'title' => 'Entregas de kits',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiaria',
                'label' => 'Beneficiária'
            ],
            [
                'key' => 'lote',
                'label' => 'Lote'
            ],
            [
                'key' => 'aprovacao',
                'label' => 'Aprovação'
            ],
            [
                'key' => 'prevista',
                'label' => 'Prevista'
            ],
            [
                'key' => 'entrega',
                'label' => 'Entrega'
            ],
            [
                'key' => 'status',
                'label' => 'Status'
            ]
        ],
        'rows' => [
            [
                'beneficiaria' => 'Ana P. Souza',
                'lote' => 'KM-08/26',
                'aprovacao' => '19/08/2026',
                'prevista' => '27/08/2026',
                'entrega' => '—',
                'status' => 'Aguardando Kit'
            ],
            [
                'beneficiaria' => 'Paula M. Reis',
                'lote' => 'KM-08/26',
                'aprovacao' => '12/08/2026',
                'prevista' => '20/08/2026',
                'entrega' => '20/08/2026',
                'status' => 'Entregue'
            ],
            [
                'beneficiaria' => 'Raimunda S. Lima',
                'lote' => 'A definir',
                'aprovacao' => '21/08/2026',
                'prevista' => 'Urgente',
                'entrega' => '—',
                'status' => 'Parto próximo'
            ]
        ],
        'primary' => 'beneficiaria'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
