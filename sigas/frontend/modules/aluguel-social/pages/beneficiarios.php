<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Beneficiários',
    'description' => 'Base vinculada ao Aluguel Social, com situação da concessão e período vigente.',
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
        'title' => 'Beneficiários',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiario',
                'label' => 'Beneficiário'
            ],
            [
                'key' => 'cpf',
                'label' => 'CPF'
            ],
            [
                'key' => 'inicio',
                'label' => 'Início'
            ],
            [
                'key' => 'fim',
                'label' => 'Fim'
            ],
            [
                'key' => 'valor',
                'label' => 'Valor'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'beneficiario' => 'Família AS-0162',
                'cpf' => '***.761.***-**',
                'inicio' => '01/07/2026',
                'fim' => '31/12/2026',
                'valor' => 'R$ 650,00',
                'situacao' => 'Ativo'
            ],
            [
                'beneficiario' => 'Família AS-0148',
                'cpf' => '***.398.***-**',
                'inicio' => '01/03/2026',
                'fim' => '31/08/2026',
                'valor' => 'R$ 600,00',
                'situacao' => 'Reavaliação'
            ]
        ],
        'primary' => 'beneficiario'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
