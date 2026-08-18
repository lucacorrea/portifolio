<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Relatórios',
    'description' => 'Central demonstrativa de relatórios operacionais e indicadores do programa.',
    'actions' => [
        ['label' => 'Exportar relatório', 'icon' => 'download', 'primary' => true],
        ['label' => 'Imprimir resumo', 'icon' => 'printer'],
    ],
    'stats' => pe_demo_stats('relatorios'),
    'search_placeholder' => 'Pesquisar relatório ou categoria',
    'filters' => [
        ['label' => 'Categoria', 'options' => ['Participantes', 'Oportunidades', 'Lotações', 'Frequência', 'Bolsas', 'Capacitações', 'Acompanhamentos']],
        ['label' => 'Situação', 'options' => ['Disponível', 'Em revisão']],
    ],
    'blocks' => [
        [
            'type' => 'chart',
            'kicker' => 'Resumo mensal',
            'title' => 'Indicadores por categoria',
            'chart' => 'bar',
            'labels' => ['Participantes', 'Oportunidades', 'Lotações', 'Frequência', 'Bolsas', 'Capacitações', 'Acompanhamentos'],
            'values' => [174, 86, 174, 160, 148, 18, 139],
        ],
        [
            'type' => 'table',
            'kicker' => 'Histórico',
            'title' => 'Relatórios recentes',
            'primary' => 'relatorio',
            'columns' => [
                ['key' => 'relatorio', 'label' => 'Relatório'], ['key' => 'categoria', 'label' => 'Categoria'],
                ['key' => 'periodo', 'label' => 'Período'], ['key' => 'gerado_em', 'label' => 'Gerado em'], ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => pe_demo_reports(),
        ],
    ],
    'modal' => ['title' => 'Detalhes do relatório'],
];
