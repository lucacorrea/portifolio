<?php
declare(strict_types=1);
$data = require dirname(__DIR__) . '/data/demo-data.php';
return sigas_frontend_page([
    'title' => 'Polos',
    'description' => 'Organização territorial demonstrativa dos pontos de distribuição.',
    'actions' => [['label' => 'Novo polo visual', 'icon' => 'geo-alt-fill', 'primary' => true]],
    'stats' => [
        ['label' => 'Polos ativos', 'value' => '8', 'detail' => '7 urbanos e 1 rural', 'icon' => 'geo-alt'],
        ['label' => 'Capacidade total', 'value' => '5.420', 'detail' => 'Famílias por ciclo', 'icon' => 'boxes'],
        ['label' => 'Equipes', 'value' => '12', 'detail' => 'Escalas demonstrativas', 'icon' => 'people'],
        ['label' => 'Ocupação média', 'value' => '92%', 'detail' => 'Capacidade utilizada', 'icon' => 'speedometer'],
    ],
    'filters' => [['label' => 'Zona', 'options' => ['Urbana', 'Rural']], ['label' => 'Situação', 'options' => ['Ativo', 'Programado']]],
    'blocks' => [[
        'type' => 'table', 'title' => 'Rede de polos',
        'columns' => [['key' => 'polo', 'label' => 'Polo'], ['key' => 'zona', 'label' => 'Zona'], ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'familias', 'label' => 'Famílias'], ['key' => 'capacidade', 'label' => 'Capacidade'], ['key' => 'situacao', 'label' => 'Situação']],
        'rows' => $data['polos'], 'primary' => 'polo',
    ]],
    'modal' => ['title' => 'Detalhes do polo'],
]);
