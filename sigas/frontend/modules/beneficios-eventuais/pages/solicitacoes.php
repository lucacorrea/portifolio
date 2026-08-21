<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Solicitações',
    'description' => 'Fila geral de solicitações de benefícios eventuais.',
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
                'key' => 'pessoa',
                'label' => 'Pessoa'
            ],
            [
                'key' => 'tipo',
                'label' => 'Tipo'
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
                'protocolo' => 'BE-2026-0814',
                'pessoa' => 'Pessoa A',
                'tipo' => 'Cesta emergencial',
                'entrada' => '21/08/2026',
                'etapa' => 'Análise',
                'prazo' => '22/08/2026'
            ],
            [
                'protocolo' => 'BE-2026-0813',
                'pessoa' => 'Pessoa B',
                'tipo' => 'Auxílio eventual',
                'entrada' => '20/08/2026',
                'etapa' => 'Entrega',
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
                'text' => 'Registro da necessidade e identificação da pessoa/família.'
            ],
            [
                'date' => '2',
                'title' => 'Triagem',
                'text' => 'Documentos, histórico de benefícios e urgência.'
            ],
            [
                'date' => '3',
                'title' => 'Análise',
                'text' => 'Critérios, parecer e definição do benefício aplicável.'
            ],
            [
                'date' => '4',
                'title' => 'Decisão',
                'text' => 'Deferimento, indeferimento ou pendência justificada.'
            ],
            [
                'date' => '5',
                'title' => 'Entrega',
                'text' => 'Registro da concessão, quantidade/valor e responsável.'
            ],
            [
                'date' => '6',
                'title' => 'Encerramento',
                'text' => 'Comprovação, histórico e conclusão do atendimento.'
            ]
        ]
    ]
],
    'demo' => true,
    'show_states' => true,
]);
