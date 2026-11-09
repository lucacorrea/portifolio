<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
return sigas_frontend_page([
    'title' => 'Relatórios',
    'description' => 'Indicadores operacionais e gerenciais do Kit Maternidade por período, território e situação.',
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
                'key' => 'abrangencia',
                'label' => 'Abrangência'
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
                'relatorio' => 'Gestantes por etapa',
                'periodo' => 'Agosto/2026',
                'abrangencia' => 'Geral',
                'atualizacao' => 'Hoje',
                'formato' => 'PDF / Excel'
            ],
            [
                'relatorio' => 'Entregas realizadas',
                'periodo' => '2026',
                'abrangencia' => 'Por território',
                'atualizacao' => 'Hoje',
                'formato' => 'PDF / Excel'
            ],
            [
                'relatorio' => 'Parto ocorrido sem kit',
                'periodo' => '2026',
                'abrangencia' => 'Prioridade',
                'atualizacao' => 'Tempo real',
                'formato' => 'PDF / Excel'
            ]
        ],
        'primary' => 'relatorio'
    ]
],
    'demo' => true,
    'show_states' => true,
]);
