<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Reuniões e atividades',
    'description' => 'Controle de participação da gestante em reuniões, orientações e atividades do programa.',
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
        'title' => 'Reuniões e atividades',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiaria',
                'label' => 'Beneficiária'
            ],
            [
                'key' => 'atividade',
                'label' => 'Atividade'
            ],
            [
                'key' => 'data',
                'label' => 'Data'
            ],
            [
                'key' => 'presenca',
                'label' => 'Presença'
            ],
            [
                'key' => 'participacao',
                'label' => 'Participação'
            ],
            [
                'key' => 'justificativa',
                'label' => 'Justificativa'
            ]
        ],
        'rows' => [
            [
                'beneficiaria' => 'Maria L. Costa',
                'atividade' => 'Orientação pré-natal',
                'data' => '15/08/2026',
                'presenca' => 'Sim',
                'participacao' => '2/3',
                'justificativa' => '—'
            ],
            [
                'beneficiaria' => 'Ana P. Souza',
                'atividade' => 'Preparação para entrega',
                'data' => '19/08/2026',
                'presenca' => 'Sim',
                'participacao' => '3/3',
                'justificativa' => '—'
            ],
            [
                'beneficiaria' => 'Carla M. Nunes',
                'atividade' => 'Reunião do programa',
                'data' => '18/08/2026',
                'presenca' => 'Não',
                'participacao' => '1/2',
                'justificativa' => 'Atestado'
            ]
        ],
        'primary' => 'beneficiaria'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
