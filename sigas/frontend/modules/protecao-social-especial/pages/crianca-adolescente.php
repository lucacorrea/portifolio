<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Criança e adolescente',
    'description' => 'Indicadores consolidados das ações protetivas voltadas a crianças e adolescentes.',
    'stats' => $stats([
        ['Acompanhamentos ativos', '96', 'Total agregado', 'emoji-smile'],
        ['Rede acionada', '71', 'No período', 'diagram-3'],
        ['Revisões previstas', '22', 'Próximos 7 dias', 'calendar3'],
        ['Retornos no prazo', '89%', 'Indicador técnico', 'check-circle'],
    ]),
    'blocks' => [
        ['type' => 'chart', 'title' => 'Acompanhamentos por faixa etária', 'chart' => 'bar', 'labels' => ['0–5', '6–9', '10–12', '13–15', '16–17'], 'values' => [14, 19, 17, 27, 19]],
        ['type' => 'info', 'title' => 'Eixos de articulação', 'items' => [
            ['icon' => 'mortarboard', 'title' => 'Educação', 'text' => 'Articulação para acesso e permanência escolar.'],
            ['icon' => 'heart-pulse', 'title' => 'Saúde', 'text' => 'Encaminhamentos e continuidade do cuidado.'],
            ['icon' => 'shield-check', 'title' => 'Sistema de garantia', 'text' => 'Fluxos protetivos acompanhados pela equipe.'],
        ]],
    ],
]);
