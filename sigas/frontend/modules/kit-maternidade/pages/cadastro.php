<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Cadastro e triagem',
    'description' => 'Entrada da gestante no programa com busca prévia no SIGAS/ANEXO e conferência dos dados.',
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
        'title' => 'Cadastro e triagem',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'referencia',
                'label' => 'Referência'
            ],
            [
                'key' => 'cpf',
                'label' => 'CPF'
            ],
            [
                'key' => 'origem',
                'label' => 'Origem'
            ],
            [
                'key' => 'resultado',
                'label' => 'Resultado'
            ],
            [
                'key' => 'acao',
                'label' => 'Próxima ação'
            ]
        ],
        'rows' => [
            [
                'referencia' => 'KM-0261',
                'cpf' => '***.871.***-**',
                'origem' => 'SIGAS',
                'resultado' => 'Pessoa já cadastrada',
                'acao' => 'Vincular ao programa'
            ],
            [
                'referencia' => 'KM-0262',
                'cpf' => '***.244.***-**',
                'origem' => 'ANEXO',
                'resultado' => 'Dados básicos encontrados',
                'acao' => 'Conferir e puxar dados'
            ],
            [
                'referencia' => 'KM-0263',
                'cpf' => '***.935.***-**',
                'origem' => 'Novo',
                'resultado' => 'Sem cadastro prévio',
                'acao' => 'Completar cadastro'
            ]
        ],
        'primary' => 'referencia'
    ],
    [
        'type' => 'timeline',
        'kicker' => 'Fluxo',
        'title' => 'Fluxo operacional',
        'items' => [
            [
                'date' => '1',
                'title' => 'Cadastro',
                'text' => 'Identificação da gestante, dados da gestação, DPP e referência territorial.'
            ],
            [
                'date' => '2',
                'title' => 'Triagem',
                'text' => 'Conferência documental e critérios iniciais do programa.'
            ],
            [
                'date' => '3',
                'title' => 'Acompanhamento',
                'text' => 'Visitas, reuniões, presença e pendências durante a gestação.'
            ],
            [
                'date' => '4',
                'title' => 'Avaliação',
                'text' => 'Parecer e decisão de aptidão para contemplação.'
            ],
            [
                'date' => '5',
                'title' => 'Entrega',
                'text' => 'Reserva, lote, termo e registro da entrega do kit.'
            ],
            [
                'date' => '6',
                'title' => 'Pós-parto',
                'text' => 'Registro do nascimento e encerramento com justificativa quando necessário.'
            ]
        ]
    ]
],
    'demo' => true,
    'show_states' => true,
]);
