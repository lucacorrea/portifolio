<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Direitos Humanos',
    'description' => 'Visão consolidada das orientações, articulações e ações de promoção de direitos.',
    'stats' => $stats([
        ['Orientações realizadas', '118', 'No mês', 'people'],
        ['Ações coletivas', '16', 'No período', 'megaphone'],
        ['Articulações da rede', '27', 'Em andamento', 'diagram-3'],
        ['Demandas encaminhadas', '49', 'Total agregado', 'send'],
    ]),
    'blocks' => [
        ['type' => 'info', 'title' => 'Eixos de atuação', 'items' => [
            ['icon' => 'chat-square-text', 'title' => 'Orientação de direitos', 'text' => 'Acolhida e informação qualificada.'],
            ['icon' => 'people', 'title' => 'Promoção da diversidade', 'text' => 'Ações educativas e comunitárias.'],
            ['icon' => 'diagram-3', 'title' => 'Articulação institucional', 'text' => 'Integração com órgãos e serviços.'],
        ]],
        ['type' => 'chart', 'title' => 'Ações por eixo', 'chart' => 'bar', 'labels' => ['Orientação', 'Promoção', 'Articulação', 'Encaminhamento'], 'values' => [118, 16, 27, 49]],
    ],
]);
