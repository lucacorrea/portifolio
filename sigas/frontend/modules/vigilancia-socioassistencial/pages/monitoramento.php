<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Monitoramento',
    'description' => 'Cobertura, capacidade, demandas, filas e tendências dos serviços socioassistenciais.',
    'actions' => [['label' => 'Comparar ciclos', 'icon' => 'arrow-left-right'], ['label' => 'Atualizar leitura', 'icon' => 'arrow-repeat', 'primary' => true]],
    'stats' => [
        ['label' => 'Cobertura média', 'value' => '70%', 'detail' => '+3 p.p. no trimestre', 'icon' => 'pie-chart'],
        ['label' => 'Capacidade utilizada', 'value' => '85%', 'detail' => 'Média dos serviços', 'icon' => 'speedometer2'],
        ['label' => 'Demandas no período', 'value' => '758', 'detail' => 'Valores sintéticos', 'icon' => 'inboxes'],
        ['label' => 'Em fila', 'value' => '111', 'detail' => '3 serviços', 'icon' => 'hourglass-split'],
    ],
    'search_placeholder' => 'Pesquisar serviço, tendência ou alerta',
    'filters' => [
        ['label' => 'Tendência', 'options' => ['Crescente', 'Estável']],
        ['label' => 'Alerta', 'options' => ['Acompanhar', 'Capacidade', 'Território']],
        ['label' => 'Período', 'options' => ['Jul/2026', '2º trimestre']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Cobertura dos serviços', 'labels' => ['PAIF', 'Convivência', 'Equipe volante'], 'values' => [82, 68, 61], 'chart' => 'bar'],
        ['type' => 'table', 'title' => 'Capacidade e demanda', 'description' => 'Leitura consolidada das filas e tendências.', 'columns' => [
            ['key' => 'servico', 'label' => 'Serviço'], ['key' => 'cobertura', 'label' => 'Cobertura'], ['key' => 'capacidade', 'label' => 'Capacidade'], ['key' => 'demandas', 'label' => 'Demandas'], ['key' => 'atendimentos', 'label' => 'Atendimentos'], ['key' => 'fila', 'label' => 'Fila'], ['key' => 'tendencia', 'label' => 'Tendência'], ['key' => 'alerta', 'label' => 'Alerta'],
        ], 'rows' => $data['monitoramento'], 'primary' => 'servico'],
        ['type' => 'info', 'title' => 'Alertas de cobertura', 'items' => [
            ['icon' => 'people', 'title' => 'Serviço de Convivência', 'text' => 'Capacidade utilizada acima de 90%.', 'badge' => 'Capacidade'],
            ['icon' => 'map', 'title' => 'Equipe volante', 'text' => 'Cobertura territorial abaixo da meta demonstrativa.', 'badge' => 'Território'],
        ]],
    ],
    'modal' => ['title' => 'Detalhes do serviço monitorado', 'fields' => ['servico', 'cobertura', 'capacidade', 'demandas', 'atendimentos', 'fila', 'tendencia', 'alerta']],
]);
