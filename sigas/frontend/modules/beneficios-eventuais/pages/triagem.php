<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Triagem',
    'description' => 'Conferência documental, urgência, histórico SIGAS/ANEXO e encaminhamento.',
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
        'title' => 'Triagem',
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
                'key' => 'historico',
                'label' => 'Histórico'
            ],
            [
                'key' => 'documentos',
                'label' => 'Documentos'
            ],
            [
                'key' => 'urgencia',
                'label' => 'Urgência'
            ],
            [
                'key' => 'resultado',
                'label' => 'Resultado'
            ]
        ],
        'rows' => [
            [
                'protocolo' => 'BE-2026-0814',
                'pessoa' => 'Pessoa A',
                'historico' => 'ANEXO: 1 entrega',
                'documentos' => 'OK',
                'urgencia' => 'Alta',
                'resultado' => 'Analisar'
            ],
            [
                'protocolo' => 'BE-2026-0812',
                'pessoa' => 'Pessoa C',
                'historico' => 'Sem registro recente',
                'documentos' => 'Pendente',
                'urgencia' => 'Alta',
                'resultado' => 'Aguardar documento'
            ]
        ],
        'primary' => 'protocolo'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
