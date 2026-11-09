<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Visitas',
    'description' => 'Agenda e execução das visitas domiciliares relacionadas ao acompanhamento gestacional.',
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
        'title' => 'Visitas',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiaria',
                'label' => 'Beneficiária'
            ],
            [
                'key' => 'prevista',
                'label' => 'Prevista'
            ],
            [
                'key' => 'realizada',
                'label' => 'Realizada'
            ],
            [
                'key' => 'profissional',
                'label' => 'Profissional'
            ],
            [
                'key' => 'status',
                'label' => 'Status'
            ],
            [
                'key' => 'proxima',
                'label' => 'Próxima ação'
            ]
        ],
        'rows' => [
            [
                'beneficiaria' => 'Maria L. Costa',
                'prevista' => '22/08/2026',
                'realizada' => '22/08/2026',
                'profissional' => 'Técnica A',
                'status' => 'Realizada',
                'proxima' => 'Reunião'
            ],
            [
                'beneficiaria' => 'Carla M. Nunes',
                'prevista' => '23/08/2026',
                'realizada' => '—',
                'profissional' => 'Técnica B',
                'status' => 'Agendada',
                'proxima' => 'Realizar visita'
            ],
            [
                'beneficiaria' => 'Elaine S. Melo',
                'prevista' => '20/08/2026',
                'realizada' => '—',
                'profissional' => 'Técnica A',
                'status' => 'Vencida',
                'proxima' => 'Reagendar'
            ]
        ],
        'primary' => 'beneficiaria'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
