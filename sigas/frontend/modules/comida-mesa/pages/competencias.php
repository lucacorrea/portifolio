<?php
declare(strict_types=1);
$data = require dirname(__DIR__) . '/data/demo-data.php';
return sigas_frontend_page([
    'title' => 'Competências',
    'description' => 'Planejamento visual dos ciclos de distribuição e acompanhamento mensal.',
    'actions' => [['label' => 'Nova competência visual', 'icon' => 'calendar-plus', 'primary' => true]],
    'stats' => [
        ['label' => 'Competência atual', 'value' => 'Jul/2026', 'detail' => 'Em andamento', 'icon' => 'calendar3'],
        ['label' => 'Famílias previstas', 'value' => '5.000', 'detail' => 'Meta do ciclo', 'icon' => 'people'],
        ['label' => 'Execução', 'value' => '94,7%', 'detail' => '4.736 entregas', 'icon' => 'graph-up'],
        ['label' => 'Próxima competência', 'value' => 'Ago/2026', 'detail' => 'Em planejamento', 'icon' => 'calendar-check'],
    ],
    'filters' => [['label' => 'Ano', 'options' => ['2026', '2025']], ['label' => 'Situação', 'options' => ['Planejada', 'Em andamento', 'Encerrada']]],
    'blocks' => [[
        'type' => 'table', 'title' => 'Ciclos de distribuição', 'description' => 'A criação real continua protegida pelo fluxo funcional existente.',
        'columns' => [['key' => 'competencia', 'label' => 'Competência'], ['key' => 'periodo', 'label' => 'Período'], ['key' => 'polos', 'label' => 'Polos'], ['key' => 'familias', 'label' => 'Famílias'], ['key' => 'entregas', 'label' => 'Entregas'], ['key' => 'situacao', 'label' => 'Situação']],
        'rows' => $data['competencias'], 'primary' => 'competencia',
    ]],
    'modal' => ['title' => 'Detalhes da competência'],
]);
