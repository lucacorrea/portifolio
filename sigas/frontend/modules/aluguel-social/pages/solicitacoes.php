<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Solicitações',
    'description' => 'Demandas registradas aguardando triagem, documentos ou encaminhamento para vistoria.',
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
        'title' => 'Solicitações',
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
                'key' => 'motivo',
                'label' => 'Motivo'
            ],
            [
                'key' => 'entrada',
                'label' => 'Entrada'
            ],
            [
                'key' => 'etapa',
                'label' => 'Etapa'
            ],
            [
                'key' => 'prazo',
                'label' => 'Prazo'
            ]
        ],
        'rows' => [
            [
                'protocolo' => 'AS-2026-0192',
                'beneficiario' => 'Família A',
                'motivo' => 'Risco estrutural',
                'entrada' => '20/08/2026',
                'etapa' => 'Triagem',
                'prazo' => '22/08/2026'
            ],
            [
                'protocolo' => 'AS-2026-0191',
                'beneficiario' => 'Família B',
                'motivo' => 'Sinistro',
                'entrada' => '19/08/2026',
                'etapa' => 'Documentos',
                'prazo' => '23/08/2026'
            ]
        ],
        'primary' => 'protocolo'
    ],
    [
        'type' => 'timeline',
        'kicker' => 'Fluxo',
        'title' => 'Fluxo operacional',
        'items' => [
            [
                'date' => '1',
                'title' => 'Solicitação',
                'text' => 'Cadastro da demanda, composição familiar, motivo e documentação.'
            ],
            [
                'date' => '2',
                'title' => 'Vistoria',
                'text' => 'Verificação do imóvel/situação de risco e relatório técnico-social.'
            ],
            [
                'date' => '3',
                'title' => 'Parecer',
                'text' => 'Análise, critérios e decisão de deferimento ou indeferimento.'
            ],
            [
                'date' => '4',
                'title' => 'Concessão',
                'text' => 'Imóvel, proprietário, período, valor e termo de concessão.'
            ],
            [
                'date' => '5',
                'title' => 'Pagamentos',
                'text' => 'Controle mensal, pendências e comprovação.'
            ],
            [
                'date' => '6',
                'title' => 'Reavaliação',
                'text' => 'Renovação, suspensão ou encerramento fundamentado.'
            ]
        ]
    ]
],
    'demo' => true,
    'show_states' => true,
]);
