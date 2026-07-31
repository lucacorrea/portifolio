<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Relatórios',
    'description' => 'Relatórios agregados de indicadores, territórios, vulnerabilidades, busca ativa e cobertura.',
    'actions' => [['label' => 'Histórico visual', 'icon' => 'clock-history'], ['label' => 'Gerar relatório', 'icon' => 'file-earmark-bar-graph', 'primary' => true]],
    'stats' => [
        ['label' => 'Modelos', 'value' => '12', 'detail' => '6 categorias', 'icon' => 'files'],
        ['label' => 'Relatórios no mês', 'value' => '27', 'detail' => 'Consultas internas', 'icon' => 'file-earmark-check'],
        ['label' => 'Diagnósticos vinculados', 'value' => '9', 'detail' => 'Acervo técnico', 'icon' => 'clipboard2-data'],
        ['label' => 'Em preparação', 'value' => '1', 'detail' => 'Estado demonstrativo', 'icon' => 'hourglass-split'],
    ],
    'search_placeholder' => 'Pesquisar relatório, tipo, recorte ou período',
    'filters' => [
        ['label' => 'Tipo', 'options' => ['Indicadores', 'Vulnerabilidade', 'Busca ativa']],
        ['label' => 'Recorte', 'options' => ['Municipal', 'Territórios', 'Urbano e rural']],
        ['label' => 'Período', 'options' => ['Jul/2026', '2º trimestre']],
    ],
    'blocks' => [
        ['type' => 'info', 'title' => 'Coleções de relatórios', 'description' => 'Exportações são demonstrativas e não enviam solicitações.', 'items' => [
            ['icon' => 'graph-up', 'title' => 'Indicadores', 'text' => 'Séries, tendências e comparações territoriais.', 'badge' => '4 modelos'],
            ['icon' => 'map', 'title' => 'Territórios e cobertura', 'text' => 'Bairros, comunidades, unidades e serviços.', 'badge' => '3 modelos'],
            ['icon' => 'exclamation-diamond', 'title' => 'Vulnerabilidades', 'text' => 'Prioridades e respostas em dados agregados.', 'badge' => '3 modelos'],
            ['icon' => 'search', 'title' => 'Busca ativa e diagnósticos', 'text' => 'Cobertura de campo e produção técnica.', 'badge' => '2 modelos'],
        ]],
        ['type' => 'chart', 'title' => 'Relatórios consultados', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [12, 15, 17, 21, 23, 27], 'chart' => 'line'],
        ['type' => 'table', 'title' => 'Relatórios recentes', 'description' => 'Histórico visual de produção e atualização.', 'columns' => [
            ['key' => 'relatorio', 'label' => 'Relatório'], ['key' => 'tipo', 'label' => 'Tipo'], ['key' => 'recorte', 'label' => 'Recorte'], ['key' => 'periodo', 'label' => 'Período'], ['key' => 'atualizacao', 'label' => 'Atualização'], ['key' => 'situacao', 'label' => 'Situação'],
        ], 'rows' => $data['relatorios'], 'primary' => 'relatorio'],
    ],
    'modal' => ['title' => 'Resumo do relatório', 'fields' => ['relatorio', 'tipo', 'recorte', 'periodo', 'atualizacao', 'situacao']],
]);
