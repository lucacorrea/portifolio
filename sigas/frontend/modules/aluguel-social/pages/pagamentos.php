<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Pagamentos',
    'description' => 'Competências mensais, valores, situação financeira e pendências de pagamento.',
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
        'title' => 'Pagamentos',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'competencia',
                'label' => 'Competência'
            ],
            [
                'key' => 'beneficiario',
                'label' => 'Beneficiário'
            ],
            [
                'key' => 'valor',
                'label' => 'Valor'
            ],
            [
                'key' => 'vencimento',
                'label' => 'Vencimento'
            ],
            [
                'key' => 'pagamento',
                'label' => 'Pagamento'
            ],
            [
                'key' => 'status',
                'label' => 'Status'
            ]
        ],
        'rows' => [
            [
                'competencia' => '08/2026',
                'beneficiario' => 'Família AS-0162',
                'valor' => 'R$ 650,00',
                'vencimento' => '10/08/2026',
                'pagamento' => '09/08/2026',
                'status' => 'Pago'
            ],
            [
                'competencia' => '08/2026',
                'beneficiario' => 'Família AS-0158',
                'valor' => 'R$ 600,00',
                'vencimento' => '10/08/2026',
                'pagamento' => '—',
                'status' => 'Pendente'
            ]
        ],
        'primary' => 'competencia'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
