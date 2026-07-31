<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Bolsa Família',
    'description' => 'Painel de acompanhamento de famílias, condicionalidades e ações de orientação.',
    'stats' => $stats([
        ['Famílias acompanhadas', '9.814', 'Competência atual', 'people'],
        ['Condicionalidades regulares', '91%', 'Indicador demonstrativo', 'check-circle'],
        ['Acompanhamentos', '238', 'Em andamento', 'clipboard2-pulse'],
        ['Ações de orientação', '14', 'No mês', 'megaphone'],
    ]),
    'filters' => [['label' => 'Acompanhamento', 'options' => ['Regular', 'Atenção', 'Em recurso']]],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Cobertura por território', 'chart' => 'bar', 'labels' => $demo['territories'], 'values' => [2140, 2480, 1920, 2084, 1190]],
        ['type' => 'info', 'title' => 'Ciclo de acompanhamento', 'items' => [
            ['icon' => 'person-check', 'title' => 'Acolhida e orientação', 'text' => 'Atendimento inicial conforme situação familiar.'],
            ['icon' => 'journal-check', 'title' => 'Registro de acompanhamento', 'text' => 'Histórico organizado por competência.'],
            ['icon' => 'arrow-left-right', 'title' => 'Articulação da rede', 'text' => 'Encaminhamentos para serviços vinculados.'],
        ]],
    ],
]);
