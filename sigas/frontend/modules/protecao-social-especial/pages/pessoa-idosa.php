<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Pessoa idosa',
    'description' => 'Indicadores agregados de proteção, acompanhamento e articulação da rede.',
    'stats' => $stats([
        ['Acompanhamentos', '52', 'Ativos', 'person-hearts'],
        ['Visitas técnicas', '31', 'No mês', 'house-check'],
        ['Rede acionada', '39', 'Ações integradas', 'diagram-3'],
        ['Reavaliações no prazo', '90%', 'Consolidado', 'check-circle'],
    ]),
    'blocks' => [
        ['type' => 'chart', 'title' => 'Demandas agregadas', 'chart' => 'doughnut', 'labels' => ['Cuidados', 'Convivência', 'Renda', 'Saúde', 'Proteção patrimonial'], 'values' => [16, 9, 7, 12, 8]],
        ['type' => 'timeline', 'title' => 'Agenda técnica agregada', 'items' => [
            ['date' => '06 ago', 'title' => 'Visitas programadas', 'text' => '8 visitas distribuídas entre as equipes.'],
            ['date' => '09 ago', 'title' => 'Articulação com saúde', 'text' => 'Revisão dos fluxos de cuidado continuado.'],
            ['date' => '13 ago', 'title' => 'Reavaliações', 'text' => '12 acompanhamentos na agenda restrita.'],
        ]],
    ],
]);
