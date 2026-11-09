<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Vistorias',
    'description' => 'Agenda e resultado das vistorias necessárias para análise do benefício.',
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
        'title' => 'Vistorias',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'protocolo',
                'label' => 'Protocolo'
            ],
            [
                'key' => 'beneficiario',
                'label' => 'Beneficiário'
            ],
            [
                'key' => 'data',
                'label' => 'Data'
            ],
            [
                'key' => 'tecnico',
                'label' => 'Técnico'
            ],
            [
                'key' => 'resultado',
                'label' => 'Resultado'
            ],
            [
                'key' => 'proxima',
                'label' => 'Próxima ação'
            ]
        ],
        'rows' => [
            [
                'protocolo' => 'AS-2026-0184',
                'beneficiario' => 'Família C',
                'data' => '23/08/2026',
                'tecnico' => 'Equipe A',
                'resultado' => 'Agendada',
                'proxima' => 'Realizar vistoria'
            ],
            [
                'protocolo' => 'AS-2026-0179',
                'beneficiario' => 'Família D',
                'data' => '19/08/2026',
                'tecnico' => 'Equipe B',
                'resultado' => 'Risco confirmado',
                'proxima' => 'Emitir parecer'
            ]
        ],
        'primary' => 'protocolo'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
