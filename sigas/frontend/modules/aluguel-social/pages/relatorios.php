<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Relatórios',
    'description' => 'Relatórios de concessões, pagamentos, vigências, motivos e reavaliações.',
    'actions' => [
    [
        'label' => 'Gerar relatório',
        'icon' => 'file-earmark-bar-graph',
        'primary' => true
    ]
],
    'stats' => [],
    'filters' => [],
    'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Operação',
        'title' => 'Relatórios',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'relatorio',
                'label' => 'Relatório'
            ],
            [
                'key' => 'periodo',
                'label' => 'Período'
            ],
            [
                'key' => 'filtro',
                'label' => 'Filtro'
            ],
            [
                'key' => 'atualizacao',
                'label' => 'Atualização'
            ],
            [
                'key' => 'formato',
                'label' => 'Formato'
            ]
        ],
        'rows' => [
            [
                'relatorio' => 'Concessões ativas',
                'periodo' => 'Atual',
                'filtro' => 'Por território',
                'atualizacao' => 'Hoje',
                'formato' => 'PDF / Excel'
            ],
            [
                'relatorio' => 'Pagamentos por competência',
                'periodo' => '2026',
                'filtro' => 'Mensal',
                'atualizacao' => 'Hoje',
                'formato' => 'PDF / Excel'
            ],
            [
                'relatorio' => 'Reavaliações e encerramentos',
                'periodo' => '2026',
                'filtro' => 'Por motivo',
                'atualizacao' => 'Hoje',
                'formato' => 'PDF / Excel'
            ]
        ],
        'primary' => 'relatorio'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
