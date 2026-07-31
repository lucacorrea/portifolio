<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Vulnerabilidades',
    'description' => 'Monitoramento agregado de vulnerabilidades, prioridades, tendências e respostas territoriais.',
    'actions' => [['label' => 'Revisar matriz', 'icon' => 'grid-3x3-gap'], ['label' => 'Registrar análise', 'icon' => 'plus-lg', 'primary' => true]],
    'stats' => [
        ['label' => 'Categorias monitoradas', 'value' => '14', 'detail' => 'Matriz municipal', 'icon' => 'exclamation-diamond'],
        ['label' => 'Alta prioridade', 'value' => '5', 'detail' => 'Resposta integrada', 'icon' => 'flag-fill'],
        ['label' => 'Tendência crescente', 'value' => '3', 'detail' => 'Último trimestre', 'icon' => 'graph-up-arrow'],
        ['label' => 'Ações em resposta', 'value' => '8', 'detail' => 'Cobertura intersetorial', 'icon' => 'arrow-repeat'],
    ],
    'search_placeholder' => 'Pesquisar vulnerabilidade, território ou ação',
    'filters' => [
        ['label' => 'Prioridade', 'options' => ['Alta', 'Média']],
        ['label' => 'Tendência', 'options' => ['Crescente', 'Estável', 'Decrescente']],
        ['label' => 'Status', 'options' => ['Em resposta', 'Monitorada', 'Em redução']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Índice demonstrativo por categoria', 'labels' => ['Alimentar', 'Territorial', 'Cadastral', 'Renda', 'Acesso'], 'values' => [82, 76, 61, 58, 69], 'chart' => 'bar'],
        ['type' => 'table', 'title' => 'Matriz de vulnerabilidades', 'description' => 'Quantidades estimadas, sem exposição de registros individuais.', 'columns' => [
            ['key' => 'tipo', 'label' => 'Vulnerabilidade'], ['key' => 'territorio', 'label' => 'Território'], ['key' => 'quantidade', 'label' => 'Quantidade estimada'], ['key' => 'prioridade', 'label' => 'Prioridade'], ['key' => 'tendencia', 'label' => 'Tendência'], ['key' => 'acoes', 'label' => 'Ações relacionadas'], ['key' => 'status', 'label' => 'Status'],
        ], 'rows' => $data['vulnerabilidades'], 'primary' => 'tipo'],
    ],
    'modal' => ['title' => 'Análise da vulnerabilidade', 'fields' => ['tipo', 'territorio', 'quantidade', 'prioridade', 'tendencia', 'acoes', 'status']],
]);
