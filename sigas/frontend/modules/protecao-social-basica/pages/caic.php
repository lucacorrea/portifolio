<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Centro de Atenção Integral à Criança — CAIC',
    'description' => 'Visão integrada dos atendimentos, atividades e articulações realizadas pelo CAIC.',
    'stats' => $stats([
        ['Crianças e adolescentes', '742', 'Cadastros ativos', 'people'],
        ['Atividades coletivas', '38', 'No mês', 'people-fill'],
        ['Atendimentos', '516', 'Últimos 30 dias', 'clipboard2-pulse'],
        ['Encaminhamentos', '87', 'Rede integrada', 'send'],
    ]),
    'blocks' => [
        ['type' => 'info', 'title' => 'Frentes de atuação', 'items' => [
            ['icon' => 'book', 'title' => 'Apoio socioeducativo', 'text' => 'Atividades coletivas por faixa etária.'],
            ['icon' => 'heart-pulse', 'title' => 'Promoção do cuidado', 'text' => 'Orientações e articulação com a rede.'],
            ['icon' => 'people', 'title' => 'Convivência familiar', 'text' => 'Encontros e atividades com responsáveis.'],
        ]],
        ['type' => 'chart', 'title' => 'Atendimentos por faixa etária', 'chart' => 'bar', 'labels' => ['0–5', '6–9', '10–12', '13–15', '16–17'], 'values' => [76, 142, 126, 109, 63]],
    ],
]);
