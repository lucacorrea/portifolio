<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Relatórios',
    'description' => 'Central visual para consultar, configurar e acompanhar relatórios gerenciais da SEMAS.',
    'actions' => [['label' => 'Histórico de exportações', 'icon' => 'clock-history'], ['label' => 'Gerar relatório', 'icon' => 'file-earmark-bar-graph', 'primary' => true]],
    'stats' => [
        ['label' => 'Modelos disponíveis', 'value' => '16', 'detail' => '5 categorias', 'icon' => 'files'],
        ['label' => 'Gerados no mês', 'value' => '38', 'detail' => 'Visualizações internas', 'icon' => 'file-earmark-check'],
        ['label' => 'Agendados', 'value' => '4', 'detail' => 'Integração futura', 'icon' => 'calendar-event'],
        ['label' => 'Em preparação', 'value' => '1', 'detail' => 'Estado demonstrativo', 'icon' => 'hourglass-split'],
    ],
    'search_placeholder' => 'Pesquisar relatório, tipo, período ou setor',
    'filters' => [
        ['label' => 'Tipo', 'options' => ['Gerencial', 'Monitoramento', 'Conformidade']],
        ['label' => 'Período', 'options' => ['Jul/2026', '2º trimestre']],
        ['label' => 'Setor', 'options' => ['Todos', 'Proteção Básica', 'Gestão']],
    ],
    'blocks' => [
        ['type' => 'info', 'title' => 'Tipos de relatório', 'description' => 'Selecione um modelo para configurar a visualização.', 'items' => [
            ['icon' => 'bullseye', 'title' => 'Metas e planos', 'text' => 'Execução, prazos e responsáveis.', 'badge' => '6 modelos'],
            ['icon' => 'buildings', 'title' => 'Rede e equipes', 'text' => 'Capacidade institucional e distribuição.', 'badge' => '4 modelos'],
            ['icon' => 'activity', 'title' => 'Monitoramento', 'text' => 'Alertas, pendências e evolução.', 'badge' => '6 modelos'],
        ]],
        ['type' => 'chart', 'title' => 'Relatórios visualizados por mês', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [18, 22, 24, 31, 29, 38], 'chart' => 'line'],
        ['type' => 'table', 'title' => 'Relatórios recentes', 'description' => 'Histórico demonstrativo de geração e disponibilidade.', 'columns' => [
            ['key' => 'relatorio', 'label' => 'Relatório'], ['key' => 'tipo', 'label' => 'Tipo'], ['key' => 'periodo', 'label' => 'Período'], ['key' => 'setor', 'label' => 'Setor'], ['key' => 'gerado', 'label' => 'Gerado em'], ['key' => 'situacao', 'label' => 'Situação'],
        ], 'rows' => $data['relatorios'], 'primary' => 'relatorio'],
    ],
    'modal' => ['title' => 'Resumo do relatório', 'fields' => ['relatorio', 'tipo', 'periodo', 'setor', 'gerado', 'situacao']],
]);
