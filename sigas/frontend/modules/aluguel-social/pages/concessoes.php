<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Concessões',
    'description' => 'Controle do imóvel, proprietário, valor e vigência da moradia temporária.',
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
        'title' => 'Concessões',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiario',
                'label' => 'Beneficiário'
            ],
            [
                'key' => 'proprietario',
                'label' => 'Proprietário'
            ],
            [
                'key' => 'imovel',
                'label' => 'Imóvel'
            ],
            [
                'key' => 'valor',
                'label' => 'Valor'
            ],
            [
                'key' => 'vigencia',
                'label' => 'Vigência'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'beneficiario' => 'Família AS-0162',
                'proprietario' => 'Proprietário A',
                'imovel' => 'Bairro Centro',
                'valor' => 'R$ 650,00',
                'vigencia' => 'Jul-Dez/2026',
                'situacao' => 'Ativa'
            ],
            [
                'beneficiario' => 'Família AS-0158',
                'proprietario' => 'Proprietário B',
                'imovel' => 'Bairro União',
                'valor' => 'R$ 600,00',
                'vigencia' => 'Mai-Out/2026',
                'situacao' => 'Ativa'
            ]
        ],
        'primary' => 'beneficiario'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
