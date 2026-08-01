<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_vigilancia_demo_data();

return sigas_frontend_page([
    'title' => 'Territórios',
    'description' => 'Caracterização agregada dos territórios, comunidades, população e unidades de referência.',
    'actions' => [['label' => 'Comparar territórios', 'icon' => 'columns-gap'], ['label' => 'Nova leitura territorial', 'icon' => 'map', 'primary' => true]],
    'stats' => [
        ['label' => 'Territórios', 'value' => '8', 'detail' => 'Recortes de monitoramento', 'icon' => 'map'],
        ['label' => 'Bairros', 'value' => '24', 'detail' => 'Zona urbana', 'icon' => 'buildings'],
        ['label' => 'Comunidades', 'value' => '31', 'detail' => 'Urbanas e rurais', 'icon' => 'houses'],
        ['label' => 'Prioritários', 'value' => '3', 'detail' => 'Resposta integrada', 'icon' => 'flag'],
    ],
    'search_placeholder' => 'Pesquisar território, unidade ou comunidade',
    'filters' => [
        ['label' => 'Vulnerabilidade', 'options' => ['Alta', 'Média']],
        ['label' => 'Situação', 'options' => ['Prioritário', 'Monitorado']],
        ['label' => 'Referência', 'options' => ['CRAS 1', 'CRAS 2', 'Equipe volante']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Famílias acompanhadas por território', 'labels' => ['Norte Urbano', 'Sul Urbano', 'Rural e Ribeirinho'], 'values' => [1126, 984, 716], 'chart' => 'bar'],
        ['type' => 'table', 'title' => 'Perfil territorial', 'description' => 'População e cobertura apresentadas em valores sintéticos.', 'columns' => [
            ['key' => 'territorio', 'label' => 'Território'], ['key' => 'bairros', 'label' => 'Bairros'], ['key' => 'comunidades', 'label' => 'Comunidades'], ['key' => 'populacao', 'label' => 'População estimada'], ['key' => 'familias', 'label' => 'Famílias'], ['key' => 'referencias', 'label' => 'Unidade de referência'], ['key' => 'vulnerabilidade', 'label' => 'Vulnerabilidade'], ['key' => 'situacao', 'label' => 'Situação'],
        ], 'rows' => $data['territorios'], 'primary' => 'territorio'],
    ],
    'modal' => ['title' => 'Resumo do território', 'fields' => ['territorio', 'bairros', 'comunidades', 'populacao', 'familias', 'referencias', 'vulnerabilidade', 'situacao']],
]);
