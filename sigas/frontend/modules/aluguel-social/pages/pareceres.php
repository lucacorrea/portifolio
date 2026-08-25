<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Pareceres',
    'description' => 'Análises técnicas e sociais com decisão fundamentada.',
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
        'title' => 'Pareceres',
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
                'key' => 'tecnico',
                'label' => 'Responsável'
            ],
            [
                'key' => 'parecer',
                'label' => 'Parecer'
            ],
            [
                'key' => 'decisao',
                'label' => 'Decisão'
            ],
            [
                'key' => 'data',
                'label' => 'Data'
            ]
        ],
        'rows' => [
            [
                'protocolo' => 'AS-2026-0179',
                'beneficiario' => 'Família D',
                'tecnico' => 'Assistente Social A',
                'parecer' => 'Favorável',
                'decisao' => 'Deferir',
                'data' => '21/08/2026'
            ],
            [
                'protocolo' => 'AS-2026-0172',
                'beneficiario' => 'Família E',
                'tecnico' => 'Assistente Social B',
                'parecer' => 'Pendência',
                'decisao' => 'Aguardar',
                'data' => '20/08/2026'
            ]
        ],
        'primary' => 'protocolo'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
